<?php

/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

include_once PG4WP_ROOT . '/driver_pgsql_rewrite.php';

/**
* Provides a driver for PostgreSQL
*
* This file maps original mysql_* functions with PostgreSQL equivalents
*
* This was originally based on usleepless's original 'mysql2pgsql.php' file, many thanks to him
*/
// Check pgsql extension is loaded
if (!extension_loaded('pgsql')) {
    wp_die('Your PHP installation appears to be missing the PostgreSQL extension which is required by WordPress with PG4WP.');
}

// Initializing some variables
$GLOBALS['pg4wp_version'] = '7.0';
$GLOBALS['pg4wp_result'] = 0;
$GLOBALS['pg4wp_numrows_query'] = '';
$GLOBALS['pg4wp_ins_table'] = '';
$GLOBALS['pg4wp_ins_field'] = '';
$GLOBALS['pg4wp_last_insert'] = '';
$GLOBALS['pg4wp_connstr'] = '';
$GLOBALS['pg4wp_conn'] = false;

/**
 * Establishes a connection to a PostgreSQL database.
 *
 * Constructs a connection string using provided parameters and attempts to
 * establish a connection to a PostgreSQL database.
 *
 * Differences from MySQL:
 * - Must connect to a specific database, unlike MySQL where you can connect without specifying a database.
 * - Uses `pg_connect` instead of `mysql_connect`.
 * - Connection string is different, using key-value pairs like 'host=', 'port=', etc.
 *
 * @param string $dbserver The server hostname and port separated by a colon.
 * @param string $dbuser The username for the database.
 * @param string $dbpass The password for the database.
 * @return resource|bool The PostgreSQL connection resource on success, or false on failure.
 * @throws WP_Error If insecure connection is attempted without setting PG4WP_INSECURE.
 */
function wpsql_connect($dbserver, $dbuser, $dbpass)
{
    $GLOBALS['pg4wp_connstr'] = '';
    $hostport = explode(':', $dbserver);

    if (!empty($hostport[0])) {
        $GLOBALS['pg4wp_connstr'] .= ' host=' . $hostport[0];
    }

    if (!empty($hostport[1])) {
        $GLOBALS['pg4wp_connstr'] .= ' port=' . $hostport[1];
    }

    if (!empty($dbuser)) {
        $GLOBALS['pg4wp_connstr'] .= ' user=' . $dbuser;
    }

    if (!empty($dbpass)) {
        $GLOBALS['pg4wp_connstr'] .= ' password=' . $dbpass;
    } elseif (!PG4WP_INSECURE) {
        wp_die('Connecting to your PostgreSQL database without a password is considered insecure.
               <br />If you want to do it anyway, please set "PG4WP_INSECURE" to true in your "db.php" file.');
    }

    // Must connect to a specific database unlike MySQL
    $dbname = defined('DB_NAME') && DB_NAME ? DB_NAME : 'template1';
    return pg_connect($GLOBALS['pg4wp_connstr'] . ' dbname=' . $dbname);
}

/**
 * Establishes a connection to a PostgreSQL database.
 *
 * This function connects to a PostgreSQL database by creating a connection string and using
 * pg_connect. If the connection is successful, it stores the PostgreSQL server version into
 * a global variable. The function also handles early transmitted SQL commands and initializes
 * the connection with pg4wp_init().
 *
 * Note: Unlike the MySQL equivalent, pg_connect will return an existing connection if one
 * exists with the same connection string.
 *
 * @param string $dbname         The name of the database to connect to.
 * @param int    $connection_id  The connection ID (Unused in this function).
 *
 * @return resource|bool The connection resource on success, or FALSE on failure.
 */
function wpsql_select_db($dbname, $connection_id = 0)
{
    $pg_connstr = $GLOBALS['pg4wp_connstr'] . ' dbname=' . $dbname;

    // pg_connect returns existing connection for same connection string
    $GLOBALS['pg4wp_conn'] = $conn = pg_connect($pg_connstr);

    // Return FALSE if connection failed
    if(!$conn) {
        return $conn;
    }

    // Get and store PostgreSQL server version
    $ver = pg_version($conn);
    if(isset($ver['server'])) {
        $GLOBALS['pg4wp_version'] = $ver['server'];
    }

    // Clear the connection string unless this is a test connection
    if(!defined('WP_INSTALLING') || !WP_INSTALLING) {
        $GLOBALS['pg4wp_connstr'] = '';
    }

    // Execute any pre-defined SQL commands
    if(!empty($GLOBALS['pg4wp_pre_sql'])) {
        foreach($GLOBALS['pg4wp_pre_sql'] as $sql2run) {
            wpsql_query($sql2run);
        }
    }

    // Initialize connection with custom function
    pg4wp_init($conn);

    return $conn;
}

/**
 * Initializes the database environment for pg4wp.
 *
 * This function sets up a MySQL-compatible `field` function in PostgreSQL.
 * Note: In MySQL, the field function accepts arguments of heterogeneous types,
 * but in PostgreSQL, it may not.
 *
 * Note: ROW_NUMBER()+unnest approach is used for performance but might not guarantee order.
 * Refer to https://stackoverflow.com/a/8767450 if it breaks.
 */
function pg4wp_init()
{
    // Database connection
    $connection = $GLOBALS['pg4wp_conn'];

    /**
     * SQL query to create or replace a PostgreSQL function named "field"
     * which imitates MySQL's FIELD() function behavior.
     *
     * - ROW_NUMBER() is a window function in Postgres, used to assign a unique integer to rows.
     * - unnest() is a Postgres function that takes an array and returns a set of rows.
     *
     * The function takes anyelement as the first parameter and anyarray as the second.
     * It returns a BIGINT.
     *
     * SQL is used as the procedural language, and the function is marked as IMMUTABLE.
     */
    $sql = <<<'SQL'
        CREATE OR REPLACE FUNCTION field(anyelement, VARIADIC anyarray)
        RETURNS BIGINT AS $$
            SELECT rownum
            FROM (
                SELECT ROW_NUMBER() OVER () AS rownum, elem
                FROM unnest($2) elem
            ) AS numbered
            WHERE numbered.elem = $1
            UNION ALL
            SELECT 0
        $$
        LANGUAGE SQL IMMUTABLE;
    SQL;

    // Execute the SQL query
    $result = pg_query($connection, $sql);

    if ((PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $result === false) {
        $error = pg_last_error($connection);
        error_log("[" . microtime(true) . "] Error creating MySQL-compatible field function: $error\n", 3, PG4WP_LOG . 'pg4wp_errors.log');
    }
}

/**
 * Executes a SQL query on a Postgres database.
 *
 * This function handles the SQL query by executing it on a Postgres database,
 * if the global connection is available. Otherwise, it stores the SQL
 * statement for later execution. It also handles errors and debugging.
 *
 * @param string $sql The SQL query string.
 * @return resource|bool The query result resource on success, or FALSE on failure.
 *
 * Differences from MySQL equivalents:
 * - Uses pg_query() instead of mysql_query() for executing the query.
 * - Error handling is done using pg_last_error() instead of mysql_error().
 * - SQL rewriting is performed by the pg4wp_rewrite() function.
 */
function wpsql_query($sql)
{
    // Check if a connection to Postgres database is established
    if (!$GLOBALS['pg4wp_conn']) {
        // Store SQL query for later execution when connection is available
        $GLOBALS['pg4wp_pre_sql'][] = $sql;
        return true;
    }

    // Store the initial SQL query
    $initial = $sql;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($sql);

    // Execute the SQL query and store the result
    if (PG4WP_DEBUG) {
        $GLOBALS['pg4wp_result'] = pg_query($GLOBALS['pg4wp_conn'], $sql);
    } else {
        $GLOBALS['pg4wp_result'] = @pg_query($GLOBALS['pg4wp_conn'], $sql);
    }

    // Handle errors and logging
    if ((PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $GLOBALS['pg4wp_result'] === false && $err = pg_last_error($GLOBALS['pg4wp_conn'])) {
        $ignore = false;
        // Ignore errors if WordPress is in the installation process
        if (defined('WP_INSTALLING') && WP_INSTALLING) {
            global $table_prefix;
            $ignore = strpos($err, 'relation "' . $table_prefix);
        }
        if (!$ignore) {
            error_log('[' . microtime(true) . "] Error running :\n$initial\n---- converted to ----\n$sql\n----> $err\n---------------------\n", 3, PG4WP_LOG . 'pg4wp_errors.log');
        }
    }

    // Return the query result
    return $GLOBALS['pg4wp_result'];
}

/**
 * Fetches a result row as an associative and numeric array from a Postgres query result.
 *
 * This function calls the pg_fetch_array() function to fetch the next row from a Postgres result set
 * and trims any leading or trailing whitespace from each of the values.
 *
 * Differences from MySQL Equivalent:
 * 1. It uses pg_fetch_array() instead of mysql_fetch_array().
 * 2. It trims the values of the resulting array, which is specific to this implementation.
 *
 * @param resource $result  The result resource returned by a Postgres query.
 * @return array|bool       An array of the next row in the result set, or false if there are no more rows.
 */
function wpsql_fetch_array($result)
{
    // Fetch the next row as an array
    $res = pg_fetch_array($result);

    // Check if the result is an array and trim its values
    if (is_array($res)) {
        foreach ($res as $v => $k) {
            $res[$v] = trim($k);
        }
    }

    // Return the trimmed array or false if there are no more rows
    return $res;
}

/**
 * Fetches the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
 *
 * @param resource|null $lnk A PostgreSQL connection resource. Default is `null`.
 *
 * @return mixed The ID generated for an AUTO_INCREMENT column by the previous INSERT query on success; `false` on failure.
 *
 * Note:
 * 1. In PostgreSQL, this function uses CURRVAL() on the appropriate sequence to get the last inserted ID.
 * 2. In MySQL, last inserted ID is generally fetched using mysql_insert_id() or mysqli_insert_id().
 */
function wpsql_insert_id($lnk = null)
{
    global $wpdb;
    $data = null;
    $ins_field = $GLOBALS['pg4wp_ins_field'];
    $table = $GLOBALS['pg4wp_ins_table'];
    $lastq = $GLOBALS['pg4wp_last_insert'];
    $seq = $table . '_seq';

    // Special case for 'term_relationships' table, which does not have a sequence in PostgreSQL.
    if ($table == $wpdb->term_relationships) {
        // PostgreSQL: Using CURRVAL() to get the current value of the sequence.
        $sql = "SELECT CURRVAL('$seq')";
        $res = pg_query($sql);
        if (false !== $res) {
            $data = pg_fetch_result($res, 0, 0);
        }
    }
    // Special case when using WP_Import plugin where ID is defined in the query itself.
    elseif ('post_author' == $ins_field && false !== strpos($lastq, 'ID')) {
        // No PostgreSQL specific operation here.
        $sql = 'ID was in query ';
        $pattern = '/.+\'(\d+).+$/';
        preg_match($pattern, $lastq, $matches);
        $data = $matches[1];

        // PostgreSQL: Setting the value of the sequence based on the latest inserted ID.
        $GLOBALS['pg4wp_queued_query'] = "SELECT SETVAL('$seq',(SELECT MAX(\"ID\") FROM $table)+1);";
    } else {
        // PostgreSQL: Using CURRVAL() to get the current value of the sequence.
        $sql = "SELECT CURRVAL('$seq')";
        $res = pg_query($GLOBALS['pg4wp_conn'], $sql);
        if (false !== $res) {
            $data = pg_fetch_result($res, 0, 0);
        } elseif (PG4WP_DEBUG || PG4WP_LOG) {
            $log = '[' . microtime(true) . "] wpsql_insert_id() was called with '$table' and '$ins_field'" .
                    " and returned the error:\n" . pg_last_error($GLOBALS['pg4wp_conn']) .
                    "\nFor the query:\n" . $sql .
                    "\nThe latest INSERT query was :\n'$lastq'\n";
            error_log($log, 3, PG4WP_LOG . 'pg4wp_errors.log');
        }
    }

    if (PG4WP_DEBUG && $sql) {
        error_log('[' . microtime(true) . "] Getting inserted ID for '$table' ('$ins_field') : $sql => $data\n", 3, PG4WP_LOG . 'pg4wp_insertid.log');
    }

    return $data;
}

/**
 * Fetch a specific result row's field value from a PostgreSQL result resource.
 *   Quick fix for wpsql_result() error and missing wpsql_errno() function
 *   Source : http://vitoriodelage.wordpress.com/2014/06/06/add-missing-wpsql_errno-in-pg4wp-plugin/
 *
 * @param resource $result  The Postgres query result resource.
 * @param int      $i       The row number from which to get the value (0-based).
 * @param string|null $fieldname  Optional. The field name to fetch.
 *
 * @return mixed  Field value or false if no such row exists.
 *
 * Note:
 * 1. This function uses `pg_fetch_result` to get a single field's value from a row.
 * 2. In MySQL, you could use `mysql_result` to accomplish something similar.
 */
function wpsql_result($result, $i, $fieldname = null)
{
    if (is_resource($result)) {
        if ($fieldname) {
            return pg_fetch_result($result, $i, $fieldname);
        } else {
            return pg_fetch_result($result, $i);
        }
    }
}

/**
 * Returns the SQLSTATE error code for the last query executed on the connection.
 *
 * @param resource $connection  The Postgres database connection resource.
 *
 * @return string|false  SQLSTATE error code or false if no error.
 *
 * Note:
 * 1. This function uses `pg_get_result` to get the result resource of the last query.
 * 2. `pg_result_status` returns the status of the result.
 * 3. `pg_result_error_field` is used to get the SQLSTATE error code.
 * 4. In MySQL, you could use `mysqli_errno` to get the error code directly.
 */
function wpsql_errno($connection)
{
    $result = pg_get_result($connection);
    if ($result === false) {
        return false;
    }

    $result_status = pg_result_status($result);
    return pg_result_error_field($result_status, PGSQL_DIAG_SQLSTATE);
}


/**
 * Checks if a connection to a PostgreSQL database is alive.
 *
 * @param resource $conn The PostgreSQL connection resource.
 * @return bool Returns true if the connection is alive, false otherwise.
 */
function wpsql_ping($conn)
{
    return pg_ping($conn);
}

/**
 * Gets the number of rows in a PostgreSQL result.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return int Returns the number of rows.
 */
function wpsql_num_rows($result)
{
    return pg_num_rows($result);
}

/**
 * Alias for wpsql_num_rows. Gets the number of rows in a PostgreSQL result.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return int Returns the number of rows.
 */
function wpsql_numrows($result)
{
    return pg_num_rows($result);
}

/**
 * Gets the number of fields in a PostgreSQL result.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return int Returns the number of fields.
 */
function wpsql_num_fields($result)
{
    return pg_num_fields($result);
}

/**
 * Mock function to mimic MySQL's fetch_field function.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return string Returns 'tablename' as a placeholder.
 */
function wpsql_fetch_field($result)
{
    return 'tablename';
}

/**
 * Fetches one row from a PostgreSQL result as an object.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return object Returns an object containing the data.
 */
function wpsql_fetch_object($result)
{
    return pg_fetch_object($result);
}

/**
 * Frees a PostgreSQL result resource.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return bool Returns true on success, false otherwise.
 */
function wpsql_free_result($result)
{
    if ($result === null) {
        return true;
    }

    return pg_free_result($result);
}

/**
 * Gets the number of affected rows by the last PostgreSQL query.
 *
 * @return int Returns the number of affected rows.
 */
function wpsql_affected_rows()
{
    if($GLOBALS['pg4wp_result'] === false) {
        return 0;
    }

    return pg_affected_rows($GLOBALS['pg4wp_result']);
}

/**
 * Fetches one row from a PostgreSQL result as an enumerated array.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return array Returns an array containing the row data.
 */
function wpsql_fetch_row($result)
{
    return pg_fetch_row($result);
}

/**
 * Sets the row offset for a PostgreSQL result resource.
 *
 * @param resource $result The PostgreSQL result resource.
 * @param int $offset The row offset.
 * @return bool Returns true on success, false otherwise.
 */
function wpsql_data_seek($result, $offset)
{
    return pg_result_seek($result, $offset);
}

/**
 * Gets the last error message from a PostgreSQL connection.
 *
 * @return string Returns the error message.
 */
function wpsql_error()
{
    if($GLOBALS['pg4wp_conn']) {
        return pg_last_error($GLOBALS['pg4wp_conn']);
    }

    return '';
}

/**
 * Fetches one row from a PostgreSQL result as an associative array.
 *
 * @param resource $result The PostgreSQL result resource.
 * @return array Returns an associative array containing the row data.
 */
function wpsql_fetch_assoc($result)
{
    return pg_fetch_assoc($result);
}

/**
 * Escapes a string for use in a PostgreSQL query.
 *
 * @param string $s The string to escape.
 * @return string Returns the escaped string.
 */
function wpsql_escape_string($s)
{
    return pg_escape_string($GLOBALS['pg4wp_conn'], $s);
}

/**
 * Escapes a string for use in a PostgreSQL query with a specified connection.
 *
 * @param string $s The string to escape.
 * @param resource $c The PostgreSQL connection resource.
 * @return string Returns the escaped string.
 */
function wpsql_real_escape_string($s, $c = null)
{
    return pg_escape_string($c, $s);
}

/**
 * Mock function to mimic MySQL's get_server_info function.
 *
 * @return string Returns '8.0.35' as a placeholder.
 */
function wpsql_get_server_info()
{
    return '8.0.35'; // Just want to fool wordpress ...
}


/**
 * Mock function to mimic MySQL's get_client_info function.
 *
 * @return string Returns '8.0.35' as a placeholder.
 */
function wpsql_get_client_info()
{
    return '8.0.35'; // Just want to fool wordpress ...
}
