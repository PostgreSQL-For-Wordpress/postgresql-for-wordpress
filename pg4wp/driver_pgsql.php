<?php

include_once PG4WP_ROOT . '/driver_pgsql_rewrite.php';

/**
* This file implements the postgreSQL driver
* This file remaps all wpsqli_* calls to postgres equivalents
*/
if (!extension_loaded('pgsql')) {
    wp_die('Your PHP installation appears to be missing the PostgreSQL extension which is required by WordPress with PG4WP.');
}

// Initializing some variables
$GLOBALS['pg4wp_version'] = '7.0';
$GLOBALS['pg4wp_result'] = 0;
$GLOBALS['pg4wp_numrows_query'] = '';
$GLOBALS['pg4wp_ins_table'] = '';
$GLOBALS['pg4wp_ins_field'] = '';
$GLOBALS['pg4wp_ins_id'] = '';
$GLOBALS['pg4wp_last_insert'] = '';
$GLOBALS['pg4wp_connstr'] = '';
$GLOBALS['pg4wp_conn'] = false;

/**
* Connection Handling
*/


/**
 * No direct equivalent in PostgreSQL. Connections are established directly.
 * Returns a fake connection class which does nothing
 */
function wpsqli_init()
{
    return new class () {
        public $sslkey;
        public $sslcert;
        public $sslca;
        public $sslcapath;
        public $sslcipher;
    };
}

/**
 * Opens a connection to a PostgreSQL server in a real context.
 *
 * This function is a wrapper for the pg_connect function, which attempts to establish
 * a connection to a PostgreSQL server. The function takes in parameters for the host name, username,
 * password, database name, port number, socket, and flags, The flags parameter can be used to set different
 * connection options that can affect the behavior of the connection.
 *
 * @param $connection dummy parameter just for compatibility with mysqli
 * @param string|null $hostname The host name or an IP address.
 * @param string|null $username The PostgreSQL user name.
 * @param string|null $password The password associated with the username.
 * @param string|null $database The default database to be used when performing queries.
 * @param int|null $port The port number to attempt to connect to the PostgreSQL server.
 * @param string|null $socket The socket or named pipe that should be used.
 * @param int $flags Client connection flags.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_real_connect(&$connection, $hostname = null, $username = null, $password = null, $database = null, $port = null, $socket = null, $flags = 0)
{
    $GLOBALS['pg4wp_connstr'] = '';

    if (!empty($hostname)) {
        $GLOBALS['pg4wp_connstr'] .= ' host=' . $hostname;
    }

    if (!empty($port)) {
        $GLOBALS['pg4wp_connstr'] .= ' port=' . $port;
    }

    if (!empty($username)) {
        $GLOBALS['pg4wp_connstr'] .= ' user=' . $username;
    }

    if (!empty($password)) {
        $GLOBALS['pg4wp_connstr'] .= ' password=' . $password;
    }

    // SSL parameters
    if (!empty($connection->sslkey)) {
        $GLOBALS['pg4wp_connstr'] .= ' sslkey=' . $connection->sslkey;
    }

    if (!empty($connection->sslcert)) {
        $GLOBALS['pg4wp_connstr'] .= ' sslcert=' . $connection->sslcert;
    }

    if (!empty($connection->sslca)) {
        $GLOBALS['pg4wp_connstr'] .= ' sslrootcert=' . $connection->sslca;
    }

    if (!empty($connection->sslcapath)) {
        $GLOBALS['pg4wp_connstr'] .= ' sslcapath=' . $connection->sslcapath;
    }

    if (!empty($connection->sslcipher)) {
        $GLOBALS['pg4wp_connstr'] .= ' sslcipher=' . $connection->sslcipher;
    }

    // Must connect to a specific database unlike MySQL
    $dbname = defined('DB_NAME') && DB_NAME ? DB_NAME : $database;
    $pg_connstr = $GLOBALS['pg4wp_connstr'] . ' dbname=' . $dbname;
    $GLOBALS['pg4wp_conn'] = $connection = pg_connect($pg_connstr);

    return $connection;
}

/**
* Database Operations
*/

/**
 * Selects the default database for database queries.
 *
 * This function is a wrapper for the pg_select_db function, which is used to change the default
 * database for the connection. This is useful when performing multiple operations across different
 * databases without having to establish a new connection for each one. If the function succeeds,
 * it will return TRUE, indicating the database was successfully selected, or FALSE if it fails.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $database The name of the database to select.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_select_db(&$connection, $database)
{
    $pg_connstr = $GLOBALS['pg4wp_connstr'] . ' dbname=' . $database;
    $GLOBALS['pg4wp_conn'] = $connection = pg_connect($pg_connstr);

    // Return FALSE if connection failed
    if(!$connection) {
        return $connection;
    }

    // Get and store PostgreSQL server version
    $ver = pg_version($connection);
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
            wpsqli_query($sql2run);
        }
    }

    return $connection;
}

/**
 * Closes a previously opened database connection.
 *
 * This function is a wrapper for the pg_close function.
 * // mysqli_close => pg_close (resource $connection): bool
 * It's important to close connections when they are no longer needed to free up resources on both the web
 * server and the PostgreSQL server. The function returns TRUE on success or FALSE on failure.
 *
 * @param PgSql\Connection $connection The pg connection resource to be closed.
 * @return bool Returns TRUE on successful closure, FALSE on failure.
 */
function wpsqli_close(&$connection)
{
    // Closing a connection in PostgreSQL is straightforward.
    return pg_close($connection);
}

/**
 * Used to establish secure connections using SSL.
 *
 * This function sets up variables on the fake pg connection class which are used when
 * connecting to the postgres database with pg_connect
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $key The path to the key file.
 * @param string $cert The path to the certificate file.
 * @param string $ca The path to the certificate authority file.
 * @param string $capath The pathname to a directory that contains trusted SSL CA certificates
 *                       in PEM format.
 * @param string $cipher A list of allowable ciphers to use for SSL encryption.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_ssl_set(&$connection, $key, $cert, $ca, $capath, $cipher)
{
    $connection->sslkey = $key;
    $connection->sslcert = $cert;
    $connection->sslca = $ca;
    $connection->sslcapath = $capath;
    $connection->sslcipher = $cipher;
    return $connection;
}

/**
 * Returns the PostgreSQL client library version as a string.
 *
 * This function is used to retrieve the version of the client library that is used to compile the pg extension. The function
 * does not require any parameters and can be called statically. It is helpful for debugging
 * and ensuring that the PHP environment is using the correct version of the PostgreSQL client library,
 * which can be important for compatibility and functionality reasons.
 *
 * @return string The PostgreSQL client library version.
 */
function wpsqli_get_client_info()
{
    // mysqli_get_client_info => No direct equivalent.
    // Information can be derived from phpinfo() or phpversion().
    return '8.0.35'; // Just want to fool wordpress ...
}

/**
 * Retrieves the version of the PostgreSQL server.
 *
 * This function returns a string representing the version of the PostgreSQL server pointed to by the connection resource. This
 * information can be used for a variety of purposes, such as conditional behavior for different
 * PostgreSQL versions or simply for logging and monitoring. Understanding the server version is
 * essential for ensuring compatibility with specific PostgreSQL features and syntax.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return string The version of the PostgreSQL server.
 */
function wpsqli_get_server_info(&$connection)
{
    // mysqli_get_server_info => pg_version (resource $connection): array
    // This function retrieves an array that includes server version.
    // pg_version($connection);
    return '8.0.35'; // Just want to fool wordpress ...
}

/**
 * Returns a string representing the type of connection used.
 *
 * This function is a wrapper for the pg_get_host_info function. It retrieves information about
 * the type of connection that was established to the PostgreSQL server and the host server information.
 * This includes the host name and the connection type, such as TCP/IP or a UNIX socket. It's useful
 * for debugging and for understanding how PHP is communicating with the PostgreSQL server.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return string A string describing the connection type and server host information.
 */
function wpsqli_host_info(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_get_host_info => No direct equivalent. Host information is part of the connection string in PostgreSQL.
}

/**
 * Pings a server connection, or tries to reconnect if the connection has gone down.
 *
 * This function is a wrapper for the pg_ping function, which checks whether the
 * connection to the server is working. If it has gone down, and the global option
 * pg.reconnect is enabled, it will attempt to reconnect. This is useful to ensure
 * that a connection is still alive and if not, to re-establish it before proceeding
 * with further operations. It returns TRUE if the connection is alive or if it was
 * successfully re-established, and FALSE if the connection is not established and
 * cannot be re-established.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_ping(&$connection)
{
    return pg_ping($connection);
}

/**
 * Returns the thread ID for the current connection.
 *
 * This function is a wrapper for the pg_thread_id function. It retrieves the thread ID used by
 * the current connection to the PostgreSQL server. This ID can be used as an argument to the KILL
 * statement to terminate a connection. It is useful for debugging and managing PostgreSQL connections
 * and can be used to uniquely identify the connection within the server's process.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return int The thread ID for the current connection.
 */
function wpsqli_thread_id(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_thread_id => No direct equivalent. PostgreSQL does not provide thread ID in the same manner as MySQL.
}

/**
 * Returns whether the client library is thread-safe.
 *
 * This function is a wrapper for the pg_thread_safe function. It indicates whether the
 * pg client library that PHP is using is thread-safe. This is important information when
 * running PHP in a multi-threaded environment such as with the worker MPM in Apache or when
 * using multi-threading extensions in PHP.
 *
 * @return bool Returns TRUE if the client library is thread-safe, FALSE otherwise.
 */
function wpsqli_thread_safe()
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_thread_safe => No direct equivalent. PostgreSQL's thread safety is dependent on PHP's thread safety.
}

/**
 * Gets the current system status of the PostgreSQL server.
 *
 * This function is a wrapper for the pg_stat function. It returns a string containing
 * status information about the PostgreSQL server to which it's connected. The information includes
 * uptime, threads, queries, open tables, and flush tables, among other status indicators.
 * This can be useful for monitoring the health and performance of the PostgreSQL server, as well
 * as for debugging purposes.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return string A string describing the server status or FALSE on failure.
 */
function wpsqli_stat(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_stat => No direct equivalent
}

/**
 * Sets extra connect options and affect behavior for a connection.
 *
 * This function is a wrapper for the pg_options function. It is used to set extra options
 * for a connection resource before establishing a connection using pg_real_connect(). These
 * options can be used to control various aspects of the connection's behavior. The function should
 * be called after pg_init() and before pg_real_connect(). It returns TRUE on success or
 * FALSE on failure.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param int $option The specific option that is to be set.
 * @param mixed $value The value for the specified option.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_options(&$connection, $option, $value)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_options => No direct equivalent. Options are set in the connection string or via set_config in PostgreSQL.
}

/**
 * Returns the error code from the last connection attempt.
 *
 * This function is a wrapper for the pg_connect_errno function. It returns the error code from
 * the last call to pg_connect() or pg_real_connect(). It is useful for error handling after
 * attempting to establish a connection to a PostgreSQL server, allowing the script to respond appropriately
 * to specific error conditions. The function does not take any parameters and returns an integer error
 * code. If no error occurred during the last connection attempt, it will return zero.
 *
 * @return int The error code from the last connection attempt.
 */
function wpsqli_connect_errno()
{
    throw new \Exception("PG4WP: Not Yet Implemented");
}

/**
 * Returns a string description of the last connect error.
 *
 * This function is a wrapper for the pg_connect_error function. It provides a textual description
 * of the error from the last connection attempt made by pg_connect() or pg_real_connect().
 * Unlike pg_connect_errno(), which returns an error code, pg_connect_error() returns a string
 * describing the error. This is useful for error handling, providing more detailed context about
 * connection problems.
 *
 * @return string|null A string that describes the error from the last connection attempt, or NULL
 *                     if no error occurred.
 */
function wpsqli_connect_error()
{
    if($GLOBALS['pg4wp_conn']) {
        return pg_last_error($GLOBALS['pg4wp_conn']);
    }

    return '';
}

/**
* Transaction Handling
*/

/**
 * Turns on or off auto-commit mode on queries for the database connection.
 *
 * This function is a wrapper for the pg_autocommit function. When turned on, each query
 * that you execute will automatically commit to the database. When turned off, you will need to
 * manually commit transactions using pg_commit() or rollback using pg_rollback(). This
 * function is particularly useful for transactions that require multiple steps and you don't want
 * to commit until all steps are successful.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param bool $mode Whether to turn on auto-commit mode or not. Pass TRUE to turn on auto-commit
 *                   mode and FALSE to turn it off.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_autocommit(&$connection, $mode)
{
    // mysqli_autocommit => pg_autocommit (resource $connection, bool $mode): bool
    // PostgreSQL autocommit behavior is typically managed at the transaction level.
    pg_query($connection, "SET AUTOCOMMIT TO ON");
}

/**
 * Starts a new transaction.
 *
 * This function is a wrapper for the pg_begin_transaction function. It starts a new transaction
 * with the provided connection and with the specified flags. Transactions allow multiple changes to
 * be made to the database atomically - they will all be applied, or none will be, which can be controlled
 * by committing or rolling back the transaction. This function can also set a name for the transaction,
 * which can be used for savepoints.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param int $flags Optional flags for defining transaction characteristics. This should be a bitmask
 *                   of any of the pg_TRANS_START_* constants.
 * @param string|null $name Optional name for the transaction, used for savepoint names.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_begin_transaction(&$connection, $flags = 0, $name = null)
{
    // mysqli_begin_transaction => pg_query (resource $connection, string $query): resource
    // PostgreSQL uses standard BEGIN or START TRANSACTION queries.
    pg_query($connection, "BEGIN");
}

/**
 * Commits the current transaction.
 *
 * This function is a wrapper for the pg_commit function. It is used to commit the current transaction
 * for the database connection. Committing a transaction means that all the operations performed since the
 * start of the transaction are permanently saved to the database. This function can also take optional flags
 * and a name, the latter being used if the commit should be associated with a named savepoint.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param int $flags Optional flags for the commit operation. It should be a bitmask of the pg_TRANS_COR_* constants.
 * @param string|null $name Optional name for the savepoint that should be committed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_commit(&$connection, $flags = 0, $name = null)
{
    // mysqli_commit => pg_query (resource $connection, string $query): resource
    // Commits are standard SQL in PostgreSQL.
    pg_query($connection, "COMMIT");
}

/**
 * Rolls back the current transaction for the database connection.
 *
 * This function is a wrapper for the pg_rollback function. It rolls back the current transaction,
 * undoing all changes made to the database in the current transaction. This is an essential feature
 * for maintaining data integrity, especially in situations where a series of database operations need
 * to be treated as an atomic unit. The function can also accept optional flags and a name, which can be
 * used to rollback to a named savepoint within the transaction rather than rolling back the entire transaction.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param int $flags Optional flags that define how the rollback operation should be handled. It should be
 *                   a bitmask of the pg_TRANS_COR_* constants.
 * @param string|null $name Optional name of the savepoint to which the rollback operation should be directed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_rollback(&$connection, $flags = 0, $name = null)
{
    // mysqli_rollback => pg_query (resource $connection, string $query): resource
    // Rollbacks are standard SQL in PostgreSQL.
    pg_query($connection, "ROLLBACK");
}

function get_primary_key_for_table(&$connection, $table)
{
    $query = <<<SQL
    SELECT a.attname, i.indisprimary 
        FROM   pg_index i 
        JOIN   pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
        WHERE  i.indrelid = '$table'::regclass
    SQL;

    $result = pg_query($connection, $query);
    if (!$result) {
        return null;
    }

    $firstRow = null;
    while ($row = pg_fetch_row($result)) {
        if ($firstRow === null) {
            $firstRow = $row; // Save the first row in case no match is found
        }
        
        if ($row[1] == true) {
            return $row[0]; // Return the first row where $row[1] == true
        }
    }

    // If no row where $row[1] == true was found, return the first row encountered
    return $firstRow ? $firstRow[0] : null;
}

/**
 * Performs a query against the database.
 *
 * This function is a wrapper for the pg_query function. The pg_query function performs
 * a query against the database and returns a result set for successful SELECT queries, or TRUE
 * for other successful DML queries such as INSERT, UPDATE, DELETE, etc.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $query The SQL query to be executed.
 * @param int $result_mode The optional mode for storing result set.
 * @return mixed Returns a pg_result object for successful SELECT queries, TRUE for other
 *               successful queries, or FALSE on failure.
 */
function wpsqli_query(&$connection, $query, $result_mode = 0)
{
    // Check if a connection to Postgres database is established
    if (!$connection) {
        // Store SQL query for later execution when connection is available
        $GLOBALS['pg4wp_pre_sql'][] = $sql;
        return true;
    }

    // Store the initial SQL query
    $initial = $query;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($query);

    // Execute the SQL query and store the result
    if (PG4WP_DEBUG) {
        $result = pg_query($connection, $sql);
    } else {
        $result = @pg_query($connection, $sql);
    }

    // Handle errors and logging
    if ((PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $result === false && $err = pg_last_error($connection)) {
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

    $GLOBALS['pg4wp_conn'] = $connection;
    $GLOBALS['pg4wp_result'] = $result;

    if (false !== strpos($sql, "INSERT INTO")) {
        $matches = array();
        preg_match("/^INSERT INTO\s+`?([a-z0-9_]+)`?/i", $query, $matches);
        $tableName = $matches[1];

        if (false !== strpos($sql, "RETURNING")) {
            $primaryKey = get_primary_key_for_table($connection, $tableName);
            $row = pg_fetch_assoc($result);

            $GLOBALS['pg4wp_ins_id'] = $row[$primaryKey];
        }
    }

    return $result;
}

/**
 * Executes one or multiple queries which are concatenated by a semicolon.
 *
 * This function is a wrapper for the pg_multi_query function. It allows execution of
 * multiple SQL statements sent to the PostgreSQL server in a single call. This can be useful to
 * perform a batch of SQL operations such as an atomic transaction that should either complete
 * entirely or not at all. After calling this function, the results of the queries can be
 * processed using pg_store_result() and pg_next_result(). It is important to ensure
 * that any user input included in the queries is properly sanitized to avoid SQL injection.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $query The queries to execute, concatenated by semicolons.
 * @return bool Returns TRUE on success or FALSE on the first error that occurred.
 *              If the first query succeeds, the function will return TRUE even if
 *              a subsequent query fails.
 */
function wpsqli_multi_query(&$connection, $query)
{
    // Store the initial SQL query
    $initial = $query;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($query);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_multi_query => No direct equivalent. Multiple queries must be executed separately in PostgreSQL.
}

/**
 * Prepares an SQL statement for execution.
 *
 * This function is a wrapper for the pg_prepare function. It prepares the SQL statement
 * and returns a statement object used for further operations on the statement. The statement
 * preparation is used to efficiently execute repeated queries with high efficiency and to avoid
 * SQL injection vulnerabilities by separating the query structure from its data. It is especially
 * useful when the same statement is executed multiple times with different parameters.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $query The SQL query to prepare.
 * @return class|false Returns a statement object on success or FALSE on failure.
 */
function wpsqli_prepare(&$connection, $query)
{
    // Store the initial SQL query
    $initial = $query;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($query);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_prepare => pg_prepare (resource $connection, string $stmtname, string $query): resource
    // pg_prepare($connection, "my_query", "SELECT * FROM my_table WHERE id = $1");
}

/**
 * Executes a prepared Query.
 *
 * This function is a wrapper for the pg_stmt_execute function. It is used to execute a statement
 * that was previously prepared using the pg_prepare function. The execution will take place with
 * the current bound parameters in the statement object. This is commonly used in database operations
 * to execute the same statement repeatedly with high efficiency and to mitigate the risk of SQL injection
 * by separating SQL logic from the data being input.
 *
 * @param pg_stmt $stmt The pg_stmt statement object.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_execute($stmt)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_stmt_execute => pg_execute (resource $connection, string $stmtname, array $params): resource
    // Executes a previously prepared statement.
    // pg_execute($connection, "my_query", array("my_id"));
}

/**
 * Binds variables to a prepared statement as parameters.
 *
 * This function is a wrapper for the pg_stmt_bind_param function. It binds variables to the
 * placeholders of a prepared statement, which is represented by the `$stmt` parameter. The `$types`
 * parameter is a string that contains one character for each variable in `$vars`, indicating the type
 * of the variable. The supported types are 'i' for integer, 'd' for double, 's' for string, and 'b' for
 * blob. By using this function, the values of the variables are bound to the statement as it is executed,
 * which can be used to safely execute the statement with user-supplied input.
 *
 * @param pg_stmt $stmt The prepared statement to which the variables are bound.
 * @param string $types A string that contains a type specification char for each variable in `$vars`.
 * @param mixed ...$vars The variables to bind to the prepared statement.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_bind_param($stmt, $types, ...$vars)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
 * Binds variables to a prepared statement for result storage.
 *
 * This function is a wrapper for the pg_stmt_bind_result function. It binds variables to the
 * prepared statement `$stmt` to store the result of the statement once it is executed. The bound
 * variables are passed by reference and will be set to the values of the corresponding columns in
 * the result set. This function is typically used in conjunction with pg_stmt_fetch(), which
 * will populate the variables with data from the next row in the result set each time it is called.
 *
 * @param pg_stmt $stmt The statement object that executed a query with a result set.
 * @param mixed &...$vars The variables to which the result set columns will be bound.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_bind_result($stmt, &...$vars)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
 * Fetches results from a prepared statement into the bound variables.
 *
 * This function is a wrapper for the pg_stmt_fetch function. It is used to fetch the data
 * from the executed prepared statement into the variables that were bound using pg_stmt_bind_result().
 * The function will return TRUE for every row fetched successfully. When there are no more rows to fetch,
 * it will return NULL, and if there is an error it will return FALSE.
 *
 * @param pg_stmt $stmt The prepared statement object from which results are to be fetched.
 * @return bool|null Returns TRUE on success, NULL if there are no more rows to fetch, or FALSE on error.
 */
function wpsqli_stmt_fetch($stmt)
{
    // Store the initial SQL query
    $initial = $query;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($query);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
 * Initializes a statement and returns an object for use with pg_stmt_prepare.
 *
 * This function is a wrapper for the pg_stmt_init function. It creates and returns a new statement
 * object associated with the specified database connection. This statement object can then be used
 * to prepare a SQL statement for execution. It's particularly useful when you need to execute a
 * prepared statement multiple times with different parameters, providing benefits such as improved
 * query performance and protection against SQL injection attacks.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return class A new statement object or FALSE on failure.
 */
function wpsqli_stmt_init(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_stmt_init => No direct equivalent in PostgreSQL.
    // In PostgreSQL, prepared statements are created directly with pg_prepare, not initialized separately.
}


/**
 * Closes a prepared statement.
 *
 * This function is a wrapper for the pg_stmt_close function. It deallocates the statement
 * and cleans up the memory associated with the statement object. This is an important step in
 * resource management, as it frees up server resources and allows other statements to be executed.
 * It should always be called after all the results have been fetched and the statement is no longer needed.
 *
 * @param pg_stmt $stmt The prepared statement object to be closed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_close($stmt)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
 * Returns a string description for the last statement error.
 *
 * This function is a wrapper for the pg_stmt_error function. It returns a string describing
 * the error for the most recent statement operation that generated an error. This is useful for
 * debugging and error handling in applications that use prepared statements to interact with the
 * PostgreSQL database. It allows developers to output or log a descriptive error message when a PostgreSQL
 * operation on a prepared statement fails.
 *
 * @param pg_stmt $stmt The pg_stmt statement object.
 * @return string A string that describes the error. An empty string if no error occurred.
 */
function wpsqli_stmt_error($stmt)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
 * Returns the error code for the most recent statement call.
 *
 * This function is a wrapper for the pg_stmt_errno function. It returns the error code from
 * the last operation performed on the specified statement. This is useful for error handling,
 * particularly in database operations where you need to react differently based on the specific
 * error that occurred. It can be used in conjunction with pg_stmt_error() to retrieve both
 * the error code and the error message for more detailed debugging and logging.
 *
 * @param pg_stmt $stmt The pg_stmt statement object.
 * @return int An error code value for the last error that occurred, or zero if no error occurred.
 */
function wpsqli_stmt_errno($stmt)
{
    // Store the initial SQL query
    $initial = $stmt;
    // Rewrite the SQL query for compatibility with Postgres
    $sql = pg4wp_rewrite($stmt);
    throw new \Exception("PG4WP: Not Yet Implemented");
    // The remaining mysqli_stmt_* functions do not have direct equivalents in PostgreSQL. Prepared statements work differently.
    // PostgreSQL uses pg_prepare() and pg_execute() for prepared statements. Results are then fetched with pg_fetch_* functions.
}

/**
* Result Handling
*/

/**
 * Fetches a result row as an associative, a numeric array, or both.
 *
 * This function is a wrapper for the pg_fetch_array function, which is used to fetch a single
 * row of data from the result set obtained from executing a SELECT query. The data can be fetched
 * as an associative array, a numeric array, or both, depending on the `mode` specified. By default,
 * it fetches as both associative and numeric (PGSQL_BOTH). Using PGSQL_ASSOC will fetch as an
 * associative array, and PGSQL_NUM will fetch as a numeric array. It returns NULL when there are
 * no more rows to fetch.
 *
 * This function calls the pg_fetch_array() function to fetch the next row from a Postgres result set
 * and trims any leading or trailing whitespace from each of the values.
 *
 * @param resource $result  The result resource returned by a Postgres query.
 * @param int $mode The type of array that should be produced from the current row data.
 * @return array|bool       An array of the next row in the result set, or false if there are no more rows.
 */
function wpsqli_fetch_array($result, $mode = PGSQL_BOTH)
{
    // Fetch the next row as an array
    $res = pg_fetch_array($result, null, $mode);

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
 * Fetches a result row as an object.
 *
 * This function is a wrapper for the pg_fetch_object function. It retrieves the current row
 * of a result set as an object where the attributes correspond to the fetched row's column names.
 * This function can instantiate an object of a specified class, and pass parameters to its constructor,
 * allowing for custom objects based on the rows of the result set. If no class is specified, it defaults
 * to a stdClass object. If the class does not exist or the specified class's constructor requires more
 * arguments than are given, an exception is thrown.
 *
 * @param \PgSql\Result $result The result set returned by pg_query, pg_store_result
 *                              or pg_use_result.
 * @param string $class The name of the class to instantiate, set the properties of which
 *                      correspond to the fetched row's column names.
 * @param array $constructor_args An optional array of parameters to pass to the constructor
 *                                for the class name defined by the class parameter.
 * @return object|false An instance of the specified class with property names that correspond
 *                      to the column names returned in the result set, or FALSE on failure.
 */
function wpsqli_fetch_object($result, $class = "stdClass", $constructor_args = [])
{
    return pg_fetch_object($result, null, $class, $constructor_args);
}

/**
 * Fetches one row of data from the result set and returns it as an enumerated array.
 * Each call to this function will retrieve the next row in the result set, so it's typically
 * used in a loop to process multiple rows.
 *
 * This function is particularly useful when you need to retrieve a row as a simple array
 * where each column is accessed by an integer index starting at 0. It does not include
 * column names as keys, which can be marginally faster and less memory intensive than
 * associative arrays if the column names are not required.
 *
 * @param \PgSql\Result $result The result set returned by a query against the database.
 *
 * @return array|null Returns an enumerated array of strings representing the fetched row,
 * or NULL if there are no more rows in the result set.
 */
function wpsqli_fetch_row($result): ?array
{
    return pg_fetch_row($result);
}

/**
 * Adjusts the result pointer to an arbitrary row in the result set represented by the
 * $result object. This function can be used in conjunction with pg_fetch_row(),
 * pg_fetch_assoc(), pg_fetch_array(), or pg_fetch_object() to navigate between
 * rows in result sets, especially when using buffered result sets.
 *
 * This is an important function for situations where you need to access a specific row
 * directly without iterating over all preceding rows, which can be useful for pagination
 * or when looking up specific rows by row number.
 *
 * @param \PgSql\Result $result The result set returned by a query against the database.
 * @param int $row_number The desired row number to seek to. Row numbers are zero-indexed.
 *
 * @return bool Returns TRUE on success or FALSE on failure. If the row number is out of range,
 * it returns FALSE.
 */
function wpsqli_data_seek($result, int $row_number): bool
{
    return pg_result_seek($result, $row_number);
}

/**
 * Returns the next field in the result set.
 *
 * This function is a wrapper for the pg_fetch_field function, which retrieves information about
 * the next field in the result set represented by the `$result` parameter. It can be used in a loop
 * to obtain information about each field in the result set, such as name, table, max length, flags,
 * and type. This is useful for dynamically generating table structures or processing query results
 * when the structure of the result set is not known in advance or changes.
 *
 * @param \PgSql\Result $result The result set returned by pg_query, pg_store_result
 *                              or pg_use_result.
 * @return object An object which contains field definition information or FALSE if no field information
 *                is available.
 */
function wpsqli_fetch_field($result)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_fetch_field => pg_field_table (resource $result, int $field_number, bool $oid_only = false): mixed
    // Returns the name or oid of the table of the field. There's no direct function to mimic mysqli_fetch_field completely.
    //pg_field_table($result, $field_number);
}

/**
 * Gets the number of fields in a result set.
 *
 * This function is a wrapper for the pg_num_fields function. It returns the number
 * of fields (columns) in a result set. This is particularly useful when you need to
 * dynamically process a query result without knowing the schema of the returned data,
 * as it allows the script to iterate over all fields in each row of the result set.
 *
 * @param \PgSql\Result $result The result set returned by pg_query, pg_store_result
 *                              or pg_use_result.
 * @return int The number of fields in the specified result set.
 */
function wpsqli_num_fields($result)
{
    // mysqli_num_fields => pg_num_fields (resource $result): int
    // Returns the number of fields (columns) in a result.
    return pg_num_fields($result);
}

/**
 * Returns the number of columns for the most recent query on the connection.
 *
 * This function is a wrapper for the pg_field_count function. It retrieves the number of
 * columns obtained from the most recent query executed on the given database connection. This
 * can be particularly useful when you need to know how many columns will be returned by a
 * SELECT statement before fetching data, which can help in dynamically processing result sets.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return int An integer representing the number of fields in the result set.
 */
function wpsqli_field_count(&$connection)
{
    // mysqli_field_count => pg_num_fields (resource $result): int
    // Use pg_num_fields to get the number of fields (columns) in a result.
    return pg_num_fields($result);
}

/**
 * Transfers a result set from the last query.
 *
 * This function is a wrapper for the pg_store_result function. It is used to transfer the
 * result set from the last query executed on the given connection, which used the pg_STORE_RESULT
 * flag. This function must be called after executing a query that returns a result set (like SELECT).
 * It allows the complete result set to be transferred to the client and then utilized via the
 * pg_fetch_* functions. It's particularly useful when the result set is expected to be accessed
 * multiple times.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return \PgSql\Result|false A buffered result object or FALSE if an error occurred.
 */
function wpsqli_store_result(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_store_result => Not needed in PostgreSQL.
    // PostgreSQL's pg_query automatically stores the results without a separate call.
    //return true;
}

/**
 * Initiates the retrieval of a result set from the last query executed using the pg_USE_RESULT mode.
 *
 * This function is a wrapper for the pg_use_result function. It is used to initiate the retrieval
 * of a result set from the last query executed on the given connection, without storing the entire result
 * set in the buffer. This is particularly useful for handling large result sets that could potentially
 * exceed the available PHP memory. The data is fetched row-by-row, reducing the immediate memory footprint.
 * However, it requires the connection to remain open, and no other operations can be performed on the
 * connection until the result set is fully processed.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return \PgSql\Result|false An unbuffered result object or FALSE if an error occurred.
 */
function wpsqli_use_result(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_use_result => Not needed in PostgreSQL.
    // PostgreSQL does not differentiate between unbuffered and buffered queries like MySQL does.
}

/**
 * Frees the memory associated with a result.
 *
 * This function is a wrapper for the pg_free_result function. It's used to free the memory
 * allocated for a result set obtained from a query. When the result data is not needed anymore,
 * it's a good practice to free the associated resources, especially when dealing with large
 * datasets that can consume significant amounts of memory. It is an important aspect of resource
 * management and helps to keep the application's memory footprint minimal.
 *
 * @param \PgSql\Result $result The result set returned by pg_query, pg_store_result
 *                              or pg_use_result.
 * @return void This function doesn't return any value.
 */
function wpsqli_free_result($result)
{
    // mysqli_free_result => pg_free_result (resource $result): bool
    // Frees memory associated with a result.
    return pg_free_result($result);
}

/**
 * Checks if there are any more result sets from a multi query.
 *
 * mysqli_more_results => No direct equivalent in PostgreSQL.
 * PostgreSQL does not support multiple results like MySQL's multi_query function.
 * It returns TRUE if one or more result sets are available from the previous calls to
 * pg_multi_query(), otherwise FALSE.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return bool Returns TRUE if there are more result sets from previous multi queries and
 *              FALSE otherwise.
 */
function wpsqli_more_results(&$connection)
{
    // mysqli_more_results => No direct equivalent in PostgreSQL.
    // PostgreSQL does not have a built-in function to check for more results from a batch of queries.
    return false;
}

/**
 * Moves the internal result pointer to the next result set returned from a multi query.
 *
 * mysqli_next_result => No direct equivalent in PostgreSQL.
 * PostgreSQL does not support multiple results like MySQL's multi_query function.
 * FALSE if there are no more result sets, or FALSE with an error if there is a problem moving the result pointer.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return bool Returns TRUE on success or FALSE on failure (no more results or an error occurred).
 */
function wpsqli_next_result(&$connection)
{
    // mysqli_next_result => No direct equivalent in PostgreSQL.
    // PostgreSQL does not support multiple results like MySQL's multi_query function.
    return false;
}

/**
* Utility Functions
*/

function wpsqli_is_resource($object)
{
    return $object !== false && $object !== null;
}

/**
 * Gets the number of affected rows in the previous PostgreSQL operation.
 *
 * This function is a wrapper for the pg_affected_rows function, which is used to determine
 * the number of rows affected by the last INSERT, UPDATE, REPLACE or DELETE query executed on
 * the given connection. It is an important function for understanding the impact of such queries,
 * allowing the developer to verify that the expected number of rows were altered. It returns an
 * integer indicating the number of rows affected or -1 if the last query failed.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return int The number of affected rows in the previous operation, or -1 if the last operation failed.
 */
function wpsqli_affected_rows(&$connection)
{
    $result = $GLOBALS['pg4wp_result'];
    // mysqli_affected_rows => pg_affected_rows (resource $result): int
    // Returns the number of rows affected by INSERT, UPDATE, or DELETE query.
    return pg_affected_rows($result);
}

// Gets the list of sequences from postgres
function wpsqli_get_list_of_sequences(&$connection)
{
    $sql = "SELECT sequencename FROM pg_sequences";
    $result = pg_query($connection, $sql);
    if(!$result) {
        if (PG4WP_DEBUG || PG4WP_LOG) {
            $log = "Unable to get list of sequences\n";
            error_log($log, 3, PG4WP_LOG . 'pg4wp_errors.log');
        }
        return [];
    }

    $data = pg_fetch_all($result);
    return array_column($data, 'sequencename');
}

// Get the primary sequence for a table
function wpsqli_get_primary_sequence_for_table(&$connection, $table)
{
    // TODO: it should be possible to use a WP transient here for object caching
    global $sequence_lookup;
    if (empty($sequence_lookup)) {
        $sequence_lookup = [];
    }

    if (isset($sequence_lookup[$table])) {
        return $sequence_lookup[$table];
    }

    $sequences = wpsqli_get_list_of_sequences($connection);
    foreach($sequences as $sequence) {
        if (strncmp($sequence, $table, strlen($table)) === 0) {
            $sequence_lookup[$table] = $sequence;
            return $sequence;
        }
    }

    // we didn't find a sequence for this table.
    return null;
}

/**
 * Fetches the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
 *
 * @param resource|null $connection A PostgreSQL connection resource. Default is `null`.
 *
 * @return mixed The ID generated for an AUTO_INCREMENT column by the previous INSERT query on success; `false` on failure.
 *
 * Note:
 * 1. In PostgreSQL, this function uses CURRVAL() on the appropriate sequence to get the last inserted ID.
 * 2. In MySQL, last inserted ID is generally fetched using mysql_insert_id() or mysqli_insert_id().
 */
function wpsqli_insert_id(&$connection = null)
{
    global $wpdb;
    $data = null;
    $ins_field = $GLOBALS['pg4wp_ins_field'];
    $table = $GLOBALS['pg4wp_ins_table'];

    if($GLOBALS['pg4wp_ins_id']) {
        return $GLOBALS['pg4wp_ins_id'];
    } elseif(empty($sql)) {
        $sql = 'NO QUERY';
        $data = 0;
    } else {
        $seq = wpsqli_get_primary_sequence_for_table($connection, $table);
        $lastq = $GLOBALS['pg4wp_last_insert'];
        // Double quoting is needed to prevent seq from being lowercased automatically
        $sql = "SELECT CURRVAL('\"$seq\"')";
        $res = pg_query($connection, $sql);
        if (false !== $res) {
            $data = pg_fetch_result($res, 0, 0);
        } elseif (PG4WP_DEBUG || PG4WP_LOG) {
            $log = '[' . microtime(true) . "] wpsqli_insert_id() was called with '$table' and '$ins_field'" .
                    " and returned the error:\n" . pg_last_error($connection) .
                    "\nFor the query:\n" . $sql .
                    "\nThe latest INSERT query was :\n'$lastq'\n";
            error_log($log, 3, PG4WP_LOG . 'pg4wp_errors.log');
        }
    }

    if (PG4WP_DEBUG && $sql) {
        error_log('[' . microtime(true) . "] Getting inserted ID for '$table' ('$ins_field') : $sql => $data\n", 3, PG4WP_LOG . 'pg4wp_insertid.log');
    }

    $GLOBALS['pg4wp_conn'] = $connection;

    return $data;
}



/**
 * Sets the default character set to be used when sending data from and to the database server.
 *
 * This function is a wrapper for the pg_set_charset function. The pg_set_charset function
 * is used to set the character set to be used when sending data from and to the database server.
 * This is particularly important to ensure that data is properly encoded and decoded when stored
 * and retrieved from the database, avoiding character encoding issues.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $charset The desired character set.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_set_charset(&$connection, $charset)
{
    // mysqli_set_charset => pg_set_client_encoding (resource $connection, string $encoding): int
    // Sets the client encoding.
    return pg_set_client_encoding($connection, "UTF8");
}

/**
 * Escapes special characters in a string for use in an SQL statement.
 *
 * This function serves as a wrapper for the pg_real_escape_string function, which is
 * utilized to escape potentially dangerous special characters within a string. This is a
 * critical security measure to prevent SQL injection vulnerabilities by ensuring that user
 * input can be safely used in an SQL query. The function takes a string to be escaped and
 * the pg connection resource, and returns the escaped string which is safe to be included
 * in SQL statements.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @param string $string The string to be escaped.
 * @return string Returns the escaped string.
 */
function wpsqli_real_escape_string(&$connection, $string)
{
    // mysqli_real_escape_string => pg_escape_string (resource $connection, string $data): string
    // Escapes a string for safe use in database queries.
    return pg_escape_string($connection, $string);
}

/**
 * Retrieves the last error description for the most recent pg function call
 * that can succeed or fail.
 *
 * This function is a wrapper for the pg_last_error function, which returns a string
 * describing the error from the last PostgreSQL operation associated with the provided
 * connection resource. It's an essential function for debugging and error handling
 * in PostgreSQL-related operations. When a pg function fails, wpsqli_error can be
 * used to fetch the corresponding error message to understand what went wrong.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return string Returns a string with the error message for the most recent function call
 *                if it has failed, or an empty string if no error has occurred.
 */
function wpsqli_error(&$connection)
{
    return pg_last_error($connection);
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
function wpsqli_errno(&$connection)
{
    $result = pg_get_result($connection);
    if ($result === false) {
        return false;
    }

    $result_status = pg_result_status($result);
    return pg_result_error_field($result_status, PGSQL_DIAG_SQLSTATE);
}

/**
 * Enables or disables internal report functions.
 *
 * This function is a wrapper for the pg_report function, which is used to set the
 * reporting mode of pg errors. This is useful for defining whether errors should be
 * reported as exceptions, warnings, or silent (no report). It's important for configuring
 * the error reporting behavior of the pg extension to suit the needs of your application,
 * particularly in a development environment where more verbose error reporting is beneficial.
 *
 * @param int $flags A bit-mask constructed from the pg_REPORT_* constants.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_report($flags)
{
    // mysqli_report => No direct equivalent in PostgreSQL.
    // MySQL's mysqli_report function is used to set what MySQLi should report, which doesn't have a direct equivalent in PostgreSQL's PHP functions.
    return true;
}

/**
 * Retrieves information about the most recently executed query.
 *
 * This function is a wrapper for the pg_info function. It provides a string containing
 * information about the most recently executed query on the given connection resource. This
 * can include information such as the number of rows affected by an INSERT, UPDATE, REPLACE,
 * or DELETE query, as well as the number of rows matched and changed. It is valuable for
 * obtaining detailed insights into the execution of database operations.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return string|null A string representing information about the last query executed,
 *                     or NULL if no information is available.
 */
function wpsqli_info(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_info => No direct equivalent in PostgreSQL.
    // This function retrieves information about the most recently executed query, which is not provided by PostgreSQL's PHP functions.
}


/**
 * Polls connections for results.
 *
 * This function is a wrapper for the pg_poll function. It can be used to poll multiple
 * connections to check if one or more of the connections have results available for client-side
 * processing. It is useful when you have multiple asynchronous queries running and need to handle
 * them as soon as their results become available. The function takes variable references for read,
 * error, and reject arrays, and modifies them to indicate which connections have results, which
 * have errors, and which were rejected respectively.
 *
 * @param array &$read Array of connections to check for outstanding results that can be read.
 * @param array &$error Array of connections on which an error occurred.
 * @param array &$reject Array of connections rejected because no asynchronous query
 *                       has been run on them.
 * @param int $sec Number of seconds to wait, must be non-negative.
 * @param int $usec Number of microseconds to wait, must be non-negative.
 * @return int|false Number of ready connections upon success, FALSE otherwise.
 */
function wpsqli_poll(&...$args)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_poll => No direct equivalent in PostgreSQL.
    // Polling for result availability is not a concept that is directly exposed in PostgreSQL's PHP functions.
    // Asynchronous query handling in PHP with PostgreSQL typically involves using separate processes or coroutines.
}

/**
 * Gets the result from asynchronous PostgreSQL query.
 *
 * This function is a wrapper for the pg_reap_async_query function. It is used after initiating
 * a query with pg_query() on a connection with the pg_ASYNC flag set. It retrieves the result
 * from the query once it is complete, which can be used with pg_poll() to manage multiple
 * asynchronous queries. It returns a pg_result object for successful SELECT queries, or TRUE for
 * other DML queries (INSERT, UPDATE, DELETE, etc.) if the operation was successful, or FALSE on failure.
 *
 * @param PgSql\Connection $connection The pg connection resource.
 * @return \PgSql\Result|bool A pg_result object for successful SELECT queries, TRUE for other
 *                            successful DML queries, or FALSE on failure.
 */
function wpsqli_reap_async_query(&$connection)
{
    throw new \Exception("PG4WP: Not Yet Implemented");
    // mysqli_reap_async_query => No direct equivalent in PostgreSQL.
    // Asynchronous queries can be executed in PostgreSQL using pg_send_query and retrieved using pg_get_result.
}
