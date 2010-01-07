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

	// List of types translations (the key is the mysql one, the value is the text to use instead)
	$GLOBALS['pg4wp_ttr'] = array(
		'bigint(20)'	=> 'bigint',
		'bigint(10)'	=> 'int',
		'int(11)'		=> 'int',
		'tinytext'		=> 'text',
		'mediumtext'	=> 'text',
		'longtext'		=> 'text',
		'unsigned'		=> '',
		'gmt datetime NOT NULL default \'0000-00-00 00:00:00\''	=> 'gmt timestamp NOT NULL DEFAULT timezone(\'gmt\'::text, now())',
		'default \'0000-00-00 00:00:00\''	=> 'DEFAULT now()',
		'datetime'		=> 'timestamp',
		'DEFAULT CHARACTER SET utf8'	=> '',
		
		// WP 2.7.1 compatibility
		'int(4)'		=> 'smallint',
		
		// ZdMultiLang support hack
		'term_id varchar(5)'	=> 'term_id int',
		'BIGINT(20)'			=> 'int',
		'character set utf8'	=> '',
		'CHARACTER SET utf8'	=> '',
		'UNSIGNED'				=> '', // ZdMultilang 1.2.5
	);
	
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
		} // CAPITALS
		
		if( 0 === strpos($sql, 'SELECT'))
		{
			$logto = 'SELECT';
			// SQL_CALC_FOUND_ROWS doesn't exist in PostgreSQL but it's needed for correct paging
			if( false !== strpos($sql, 'SQL_CALC_FOUND_ROWS'))
			{
				$catchnumrows = true;
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
			
			$date_funcs = array(
				'YEAR('		=> 'EXTRACT(YEAR FROM ',
				'MONTH('	=> 'EXTRACT(MONTH FROM ',
				'DAY('		=> 'EXTRACT(DAY FROM ',
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
			
			// To avoid Encoding errors when inserting data coming from outside
			if( preg_match('/^.{1}/us',$sql,$ar) != 1)
				$sql = utf8_encode($sql);
			
		} // INSERT
		elseif( 0 === strpos( $sql, 'DELETE' ))
		{
			$logto = 'DELETE';
			// LIMIT is not allowed in DELETE queries
			$sql = str_replace( 'LIMIT 1', '', $sql);
		}
		// Fix tables listing
		elseif( 0 === strpos($sql, 'SHOW TABLES'))
		{
			$logto = 'SHOWTABLES';
			$sql = 'SELECT tablename FROM pg_tables WHERE schemaname = \'public\';';
		}
		// SHOW INDEX emulation
		elseif( 0 === strpos( $sql, 'SHOW INDEX'))
		{
			$logto = 'SHOWINDEX';
			$pattern = '/SHOW INDEX FROM\s+(\w+)/';
			preg_match( $pattern, $sql, $matches);
			$table = $matches[1];
$sql = 'SELECT bc.relname AS "Table",
	CASE WHEN i.indisunique THEN \'0\' ELSE \'1\' END AS "Non_unique",
	CASE WHEN i.indisprimary THEN \'PRIMARY\' WHEN bc.relname LIKE \'%usermeta\' AND ic.relname = \'umeta_key\' THEN \'meta_key\' ELSE ic.relname END AS "Key_name",
	a.attname AS "Column_name",
	NULL AS "Sub_part"
FROM pg_class bc, pg_class ic, pg_index i, pg_attribute a
WHERE bc.oid = i.indrelid
	AND ic.oid = i.indexrelid
	AND (i.indkey[0] = a.attnum OR i.indkey[1] = a.attnum OR i.indkey[2] = a.attnum OR i.indkey[3] = a.attnum OR i.indkey[4] = a.attnum OR i.indkey[5] = a.attnum OR i.indkey[6] = a.attnum OR i.indkey[7] = a.attnum)
	AND a.attrelid = bc.oid
	AND bc.relname = \''.$table.'\'
	ORDER BY a.attname;';
		}
		// Table alteration
		elseif( 0 === strpos( $sql, 'ALTER TABLE'))
		{
			$logto = 'ALTER';
			$pattern = '/ALTER TABLE\s+(\w+)\s+CHANGE COLUMN\s+([^\s]+)\s+([^\s]+)\s+([^ ]+)( unsigned|)\s+(NOT NULL|)\s*(default (.+)|)/';
			if( 1 === preg_match( $pattern, $sql, $matches))
			{
				$table = $matches[1];
				$col = $matches[2];
				$newname = $matches[3];
				$type = $matches[4];
				if( isset($GLOBALS['pg4wp_ttr'][$type]))
					$type = $GLOBALS['pg4wp_ttr'][$type];
				$unsigned = $matches[5];
				$notnull = $matches[6];
				$default = $matches[7];
				$defval = $matches[8];
				if( isset($GLOBALS['pg4wp_ttr'][$defval]))
					$defval = $GLOBALS['pg4wp_ttr'][$defval];
				$newq = "ALTER TABLE $table ALTER COLUMN $col TYPE $type";
				if( !empty($notnull))
					$newq .= ", ALTER COLUMN $col SET NOT NULL";
				if( !empty($default))
					$newq .= ", ALTER COLUMN $col SET DEFAULT $defval";
				if( $col != $newname)
					$newq .= ";ALTER TABLE $table RENAME COLUMN $col TO $newcol;";
				$sql = $newq;
			}
			$pattern = '/ALTER TABLE\s+(\w+)\s+ADD (UNIQUE |)KEY\s+([^\s]+)\s+\(([^\)]+)\)/';
			if( 1 === preg_match( $pattern, $sql, $matches))
			{
				$table = $matches[1];
				$unique = $matches[2];
				$index = $matches[3];
				$columns = $matches[4];
				// Workaround for index name duplicate
				if( $table == $table_prefix.'usermeta' && $index == 'meta_key')
					$index = 'umeta_key';
				$sql = "CREATE {$unique}INDEX $index ON $table ($columns)";
			}
		}
		// Table description
		elseif( 0 === strpos( $sql, 'DESCRIBE'))
		{
			$logto = 'DESCRIBE';
			preg_match( '/DESCRIBE\s+(\w+)/', $sql, $matches);
			$table_name = $matches[1];
$sql = "SELECT pg_attribute.attname AS \"Field\",
	CASE pg_type.typname
		WHEN 'int2' THEN 'int(4)'
		WHEN 'int4' THEN 'int(11)'
		WHEN 'int8' THEN 'bigint(20) unsigned'
		WHEN 'varchar' THEN 'varchar(' || pg_attribute.atttypmod-4 || ')'
		WHEN 'timestamp' THEN 'datetime'
		WHEN 'text' THEN 'longtext'
		ELSE pg_type.typname
	END AS \"Type\",
	CASE WHEN pg_attribute.attnotnull THEN ''
		ELSE 'YES'
	END AS \"Null\",
	CASE pg_type.typname
		WHEN 'varchar' THEN substring(pg_attrdef.adsrc FROM '^\'(.*)\'.*$')
		WHEN 'timestamp' THEN CASE WHEN pg_attrdef.adsrc LIKE '%now()%' THEN '0000-00-00 00:00:00' ELSE pg_attrdef.adsrc END
		ELSE pg_attrdef.adsrc
	END AS \"Default\"
FROM pg_class
	INNER JOIN pg_attribute
		ON (pg_class.oid=pg_attribute.attrelid)
	INNER JOIN pg_type
		ON (pg_attribute.atttypid=pg_type.oid)
	LEFT JOIN pg_attrdef
		ON (pg_class.oid=pg_attrdef.adrelid AND pg_attribute.attnum=pg_attrdef.adnum)
WHERE pg_class.relname='$table_name' AND pg_attribute.attnum>=1 AND NOT pg_attribute.attisdropped;";
		} // DESCRIBE
		// Fix table creations
		elseif( 0 === strpos($sql, 'CREATE TABLE'))
		{
			$logto = 'CREATE';
			$pattern = '/CREATE TABLE (\w+)/';
			preg_match($pattern, $sql, $matches);
			$table = $matches[1];
			
			// Remove trailing spaces
			$sql = trim( $sql).';';
			
			// Translate types and some other replacements
			$sql = str_replace(
				array_keys($GLOBALS['pg4wp_ttr']), array_values($GLOBALS['pg4wp_ttr']), $sql);
			
			// Fix auto_increment by adding a sequence
			$pattern = '/int[ ]+NOT NULL auto_increment/';
			preg_match($pattern, $sql, $matches);
			if($matches)
			{
				$seq = $table . '_seq';
				$sql = str_replace( 'NOT NULL auto_increment', "NOT NULL DEFAULT nextval('$seq'::text)", $sql);
				$sql .= "\nCREATE SEQUENCE $seq;";
			}
			
			// Support for INDEX creation
			$pattern = '/,\s+(UNIQUE |)KEY\s+([^\s]+)\s+\(([^\)]+)\)/';
			if( preg_match_all( $pattern, $sql, $matches, PREG_SET_ORDER))
				foreach( $matches as $match)
				{
					$unique = $match[1];
					$index = $match[2];
					$columns = $match[3];
					// Workaround for index name duplicate
					if( $table == $table_prefix.'usermeta' && $index == 'meta_key')
						$index = 'umeta_key';
					$sql .= "\nCREATE {$unique}INDEX $index ON $table ($columns);";
				}
			// Now remove handled indexes
			$sql = preg_replace( $pattern, '', $sql);
		}// CREATE TABLE
		
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
		if( PG4WP_DEBUG && $GLOBALS['pg4wp_result'] === false && $err = pg_last_error())
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
