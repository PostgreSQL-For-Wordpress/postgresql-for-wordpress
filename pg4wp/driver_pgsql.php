<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

/**
* Provides a driver for PostgreSQL
*
* This file maps original mysql_* functions with PostgreSQL equivalents
*
* This was originally based on usleepless's original 'mysql2pgsql.php' file, many thanks to him
*/

	// Load up upgrade and install functions as required
	if( defined( 'WP_INSTALLING') && WP_INSTALLING)
		require_once( PG4WP_ROOT.'/driver_pgsql_install.php');

	// Initializing some variables
	$GLOBALS['pg4wp_result'] = 0;
	$GLOBALS['pg4wp_numrows'] = '10';
	$GLOBALS['pg4wp_ins_table'] = '';
	$GLOBALS['pg4wp_ins_field'] = '';
	$GLOBALS['pg4wp_user'] = $GLOBALS['pg4wp_password'] = $GLOBALS['pg4wp_server'] = '';
	
	function wpsql_num_rows($result)
		{ return pg_num_rows($result); }
	function wpsql_numrows($result)
		{ return pg_num_rows($result); }
	function wpsql_num_fields($result)
		{ return 1; }
	function wpsql_fetch_field($result)
		{ return 'tablename'; }
	function wpsql_fetch_object($result)
		{ return pg_fetch_object($result); }
	function wpsql_free_result($result)
		{ return pg_free_result($result); }
	function wpsql_affected_rows()
	{
		if( $GLOBALS['pg4wp_result'] === false)
			return 0;
		else
			return pg_affected_rows($GLOBALS['pg4wp_result']);
	}
	function wpsql_fetch_row($result)
		{ return pg_fetch_row($result); }
	function wpsql_data_seek($result, $offset)
		{ return pg_result_seek ( $result, $offset ); }
	function wpsql_error()
		{ if( $GLOBALS['pg4wp_user'] == '') return pg_last_error(); else return ''; }
	function wpsql_fetch_assoc($result) { return pg_fetch_assoc($result); }
	function wpsql_escape_string($s) { return pg_escape_string($s); }
	function wpsql_get_server_info() { return '4.1.3'; } // Just want to fool wordpress ...
	function wpsql_result($result, $i, $fieldname)
		{ return pg_fetch_result($result, $i, $fieldname); }

	// This is a fake connection
	function wpsql_connect($dbserver, $dbuser, $dbpass)
	{
		$GLOBALS['pg4wp_user'] = $dbuser;
		$GLOBALS['pg4wp_password'] = $dbpass;
		$GLOBALS['pg4wp_server'] = $dbserver;
		return 1;
	}

	function wpsql_select_db($dbname, $connection_id = 0)
	{
		$pg_user = $GLOBALS['pg4wp_user'];
		$pg_password = $GLOBALS['pg4wp_password'];
		$pg_server = $GLOBALS['pg4wp_server'];
		if( empty( $pg_server))
			$conn = pg_connect("user=$pg_user password=$pg_password dbname=$dbname");
		else
			$conn = pg_connect("host=$pg_server user=$pg_user password=$pg_password dbname=$dbname");
		// Now we should be connected, we "forget" about the connection parameters
		$GLOBALS['pg4wp_user'] = '';
		$GLOBALS['pg4wp_password'] = '';
		$GLOBALS['pg4wp_server'] = '';
		return $conn;
	}

	function wpsql_fetch_array($result)
	{
		$res = pg_fetch_array($result);
		
		if( is_array($res) )
		foreach($res as $v => $k )
			$res[$v] = trim($k);
		return $res;
	}
	
	function wpsql_query($sql)
	{
		global $table_prefix;
		$logto = 'queries';
		// This is used to catch the number of rows returned by the last "SELECT" REQUEST
		$catchnumrows = false;
		
		// Remove unusefull spaces
		$initial = $sql = trim($sql);
		
		// Remove illegal characters
		$sql = str_replace('`', '', $sql);
		
		if( 0 === strpos($sql, 'SELECT'))
		{
			$logto = 'SELECT';
			// SQL_CALC_FOUND_ROWS doesn't exist in PostgreSQL but it's needed for correct paging
			if( false !== strpos($sql, 'SQL_CALC_FOUND_ROWS'))
			{
				$catchnumrows = true;
				$sql = str_replace('GROUP BY '.$table_prefix.'posts.ID', '' , $sql);
				$sql = str_replace('SQL_CALC_FOUND_ROWS', 'DISTINCT', $sql);
				$GLOBALS['pg4wp_numrows'] = preg_replace( '/SELECT DISTINCT.+FROM ('.$table_prefix.'posts)/', 'SELECT DISTINCT "ID" FROM $1', $sql);
				$GLOBALS['pg4wp_numrows'] = preg_replace( '/SELECT(.+)FROM/', 'SELECT COUNT($1) FROM', $GLOBALS['pg4wp_numrows']);
				$GLOBALS['pg4wp_numrows'] = preg_replace( '/(ORDER BY|LIMIT).+/', '', $GLOBALS['pg4wp_numrows']);
			}
			elseif( false !== strpos($sql, 'FOUND_ROWS()'))
			{
				$sql = $GLOBALS['pg4wp_numrows'];
			}
			
			$pattern = '/LIMIT[ ]+(\d+),[ ]*(\d+)/';
			$sql = preg_replace($pattern, 'LIMIT $2 OFFSET $1', $sql);
			
			$sql = str_replace('INTERVAL 120 MINUTE', "'120 minutes'::interval", $sql);
			
			$pattern = '/DATE_ADD[ ]*\(([^,]+),([^\)]+)\)/';
			$sql = preg_replace( $pattern, '($1 + $2)', $sql);
			
			// UNIX_TIMESTAMP in MYSQL returns an integer
			$pattern = '/UNIX_TIMESTAMP\(([^\)])\)/';
			$sql = preg_replace( $pattern, 'ROUND(DATE_PART(\'epoch\',$1))', $sql);
			
			$date_funcs = array(
				'YEAR('			=> 'EXTRACT(YEAR FROM ',
				'MONTH('		=> 'EXTRACT(MONTH FROM ',
				'DAY('			=> 'EXTRACT(DAY FROM ',
				'DAYOFMONTH('	=> 'EXTRACT(DAY FROM ',
			);
			
			$sql = str_replace( 'ORDER BY post_date DESC', 'ORDER BY YEAR(post_date) DESC, MONTH(post_date) DESC', $sql);
			$sql = str_replace( array_keys($date_funcs), array_values($date_funcs), $sql);
			
			// MySQL 'IF' conversion
			$pattern = '/IF[ ]*\(([^,]+),([^,]+),([^\)]+)\)/';
			$sql = preg_replace( $pattern, 'CASE WHEN $1 THEN $2 ELSE $3 END', $sql);
			
			$sql = str_replace('GROUP BY '.$table_prefix.'posts."ID"', '' , $sql);
			$sql = str_replace("!= ''", '<> 0', $sql);
			
			// MySQL 'LIKE' is case insensitive by default, whereas PostgreSQL 'LIKE' is
			$sql = str_replace( ' LIKE ', ' ILIKE ', $sql);
			
			// INDEXES are not yet supported
			if( false !== strpos( $sql, 'USE INDEX (comment_date_gmt)'))
				$sql = str_replace( 'USE INDEX (comment_date_gmt)', '', $sql);
			
			// WP 2.9.1 uses a comparison where text data is not quoted
			$pattern = '/AND meta_value = (-?\d+)/';
			$sql = preg_replace( $pattern, 'AND meta_value = \'$1\'', $sql);
			
			// ZdMultiLang support hacks
			$sql = preg_replace( '/post_type="([^"]+)"/', 'post_type=\'$1\'', $sql);
			$sql = str_replace( 'link_url o_url', 'link_url AS o_url', $sql);
			$sql = str_replace( 'link_name o_name', 'link_name AS o_name', $sql);
			$sql = str_replace( 'link_description o_desc', 'link_description AS o_desc', $sql);
		} // SELECT
		elseif( 0 === strpos($sql, 'UPDATE'))
		{
			$logto = 'UPDATE';
			$pattern = '/LIMIT[ ]+\d+/';
			$sql = preg_replace($pattern, '', $sql);
			
			// WP 2.6.1 => 2.8 upgrade, removes a PostgreSQL error but there are some remaining
			$sql = str_replace( "post_date = '0000-00-00 00:00:00'", "post_date IS NULL", $sql);
			
		} // UPDATE
		elseif( 0 === strpos($sql, 'INSERT'))
		{
			$logto = 'INSERT';
			$sql = str_replace('(0,',"('0',", $sql);
			$sql = str_replace('(1,',"('1',", $sql);
			$pattern = '/INSERT INTO (\w+)\s+\([ a-zA-Z_"]+/';
			preg_match($pattern, $sql, $matches);
			$GLOBALS['pg4wp_ins_table'] = $matches[1];
			$match_list = split(' ', $matches[0]);
			if( $GLOBALS['pg4wp_ins_table'])
			{
				$GLOBALS['pg4wp_ins_field'] = trim($match_list[3],' ()	');
				if(! $GLOBALS['pg4wp_ins_field'])
					$GLOBALS['pg4wp_ins_field'] = trim($match_list[4],' ()	');
			}
			
			// ZdMultiLang support hack
			if( $GLOBALS['pg4wp_ins_table'] == $table_prefix.'zd_ml_trans')
			{
				preg_match( '/VALUES \([^\d]*(\d+)', $sql, $matches);
				$GLOBALS['pg4wp_insid'] = $matches[1];
			}
			
			// Fix inserts into wp_categories
			if( false !== strpos($sql, 'INSERT INTO '.$table_prefix.'categories'))
			{
				$sql = str_replace('"cat_ID",', '', $sql);
				$sql = str_replace("VALUES ('0',", "VALUES(", $sql);
			}
			
			// Those are used when we need to set the date to now() in gmt time
			$sql = str_replace( "'0000-00-00 00:00:00'", 'now() AT TIME ZONE \'gmt\'', $sql);
			
			// Multiple values group when calling INSERT INTO don't always work
			if( false !== strpos( $sql, $table_prefix.'options') && false !== strpos( $sql, '), ('))
			{
				$pattern = '/INSERT INTO.+VALUES/';
				preg_match($pattern, $sql, $matches);
				$insert = $matches[0];
				$sql = str_replace( '), (', ');'.$insert.'(', $sql);
			}
			
			// Support for "INSERT ... ON DUPLICATE KEY UPDATE ..." is a dirty hack
			// consisting in deleting the row before inserting it
			if( false !== $pos = strpos( $sql, 'ON DUPLICATE KEY'))
			{
				// Remove 'ON DUPLICATE KEY UPDATE...' and following
				$sql = substr( $sql, 0, $pos);
				// Get the elements we need (table name, first field, value)
				$pattern = '/INSERT INTO (\w+)\s+\(([^,]+).+VALUES\s+\(([^,]+)/';
				preg_match($pattern, $sql, $matches);
				$sql = 'DELETE FROM '.$matches[1].' WHERE '.$matches[2].' = '.$matches[3].';'.$sql;
			}
			
			// To avoid Encoding errors when inserting data coming from outside
			if( preg_match('/^.{1}/us',$sql,$ar) != 1)
				$sql = utf8_encode($sql);
			
		} // INSERT
		elseif( 0 === strpos( $sql, 'DELETE' ))
		{
			$logto = 'DELETE';
			// LIMIT is not allowed in DELETE queries
			$sql = str_replace( 'LIMIT 1', '', $sql);
			$sql = str_replace( ' REGEXP ', ' ~ ', $sql);
			
			// This handles removal of duplicate entries in table options
			if( false !== strpos( $sql, 'DELETE o1 FROM '))
				$sql = "DELETE FROM ${table_prefix}options WHERE option_id IN " .
					"(SELECT o1.option_id FROM ${table_prefix}options AS o1, ${table_prefix}options AS o2 " .
					"WHERE o1.option_name = o2.option_name " .
					"AND o1.option_id < o2.option_id)";
		}
		// Fix tables listing
		elseif( 0 === strpos($sql, 'SHOW TABLES'))
		{
			$logto = 'SHOWTABLES';
			$sql = 'SELECT tablename FROM pg_tables WHERE schemaname = \'public\';';
		}
		elseif( defined('WP_INSTALLING') && WP_INSTALLING)
			$sql = pg4wp_installing( $sql, $logto);
		
		// The following handles a new "INTERVAL" call in Akismet 2.2.7
		$sql = str_replace('INTERVAL 15 DAY', "'15 days'::interval", $sql);
		$pattern = '/DATE_SUB[ ]*\(([^,]+),([^\)]+)\)/';
		$sql = preg_replace( $pattern, '($1::timestamp - $2)', $sql);
		
		// Field names with CAPITALS need special handling
		if( false !== strpos($sql, 'ID'))
		{
			$pattern = '/ID([^ ])/';
				$sql = preg_replace($pattern, 'ID $1', $sql);
			$pattern = '/ID$/';
				$sql = preg_replace($pattern, 'ID ', $sql);
			$pattern = '/\(ID/';
				$sql = preg_replace($pattern, '( ID', $sql);
			$pattern = '/,ID/';
				$sql = preg_replace($pattern, ', ID', $sql);
			$pattern = '/[a-zA-Z_]+ID/';
				$sql = preg_replace($pattern, '"$0"', $sql);
			$pattern = '/\.ID/';
				$sql = preg_replace($pattern, '."ID"', $sql);
			$pattern = '/[\s]ID /';
				$sql = preg_replace($pattern, ' "ID" ', $sql);
			$pattern = '/"ID "/';
				$sql = preg_replace($pattern, ' "ID" ', $sql);
		} // CAPITALS
		
		// Empty "IN" statements are erroneous
		$sql = str_replace( 'IN (\'\')', 'IN (NULL)', $sql);
		$sql = str_replace( 'IN ( \'\' )', 'IN (NULL)', $sql);
		$sql = str_replace( 'IN ()', 'IN (NULL)', $sql);
		
		// ZdMultiLang 1.2.4 uses a lowercase 'in'
		$sql = str_replace( 'in ()', 'IN (NULL)', $sql);
		
		// ZdMultiLang support hack, some columns have capitals in their name, and that needs special handling for PostgreSQL
		if( false !== strpos( $sql, $table_prefix.'zd_ml_langs'))
		{
			$zdml_conv = array(
				'LanguageName'		=> '"LanguageName"',
				'LangPermalink'		=> '"LangPermalink"',
				'BlogName'			=> '"BlogName"',
				'BlogDescription'	=> '"BlogDescription"',
			);
			$sql = str_replace( array_keys($zdml_conv), array_values($zdml_conv), $sql);
		}
		
		if( PG4WP_DEBUG)
		{
			if( $initial != $sql)
				error_log("Converting :\n$initial\n---- to ----\n$sql\n---------------------\n", 3, PG4WP_LOG.'pg4wp_'.$logto.'.log');
			else
				error_log("$sql\n---------------------\n", 3, PG4WP_LOG.'pg4wp_unmodified.log');
		}
		$GLOBALS['pg4wp_result'] = pg_query($sql);
		if( (PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $GLOBALS['pg4wp_result'] === false && $err = pg_last_error())
			if( false === strpos($err, 'relation "'.$table_prefix.'options"'))
				error_log("Error running :\n$initial\n---- converted to ----\n$sql\n----\n$err\n---------------------\n", 3, PG4WP_LOG.'pg4wp_errors.log');
		
		return $GLOBALS['pg4wp_result'];
	}
	
	function wpsql_insert_id($table)
	{
		global $table_prefix;
		$ins_field = $GLOBALS['pg4wp_ins_field'];
		
		$tbls = split("\n", $GLOBALS['pg4wp_ins_table']); // Workaround for bad tablename
		$t = $tbls[0] . '_seq';
		
		// ZdMultiLang support hack
		if( $tbls[0] == $table_prefix.'zd_ml_trans')
			return $GLOBALS['pg4wp_insid'];
		
		if( in_array( $t, array( '_seq', $table_prefix.'term_relationships_seq')))
			return 0;
		
		if( $ins_field == '"cat_ID"' || $ins_field == 'rel_id')
			$sql = "SELECT MAX($ins_field) FROM $ins_table";
		else
			$sql = "SELECT CURRVAL('$t')";
		
		$res = pg_query($sql);
		$data = pg_fetch_result($res, 0, 0);
		if( PG4WP_DEBUG && $sql)
			error_log("Getting : $sql => $data\n", 3, PG4WP_LOG.'pg4wp_insertid.log');
		return $data;
	}
