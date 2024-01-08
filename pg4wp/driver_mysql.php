<?php

/**
* This file implements a mysql reference driver
* This driver should do nothing different vs a standard install
* This file remaps all wpsqli_* calls to mysqli_* original name
*/

/**
* Connection Handling
*/

/**
 * Initializes MySQLi and returns a resource for use with mysqli_real_connect().
 *
 * This function is a wrapper for the mysqli_init function, which initializes and returns
 * a resource for use with mysqli_real_connect(). It is part of the MySQLi extension's
 * object-oriented interface and is usually employed to prepare for a secure connection
 * using settings like mysqli_ssl_set() before establishing a connection to a MySQL server.
 * The function does not require any parameters and will return a mysqli object on success
 * or FALSE on failure.
 *
 * @return mysqli|false Returns an object which can be used with mysqli_real_connect() or
 *                      FALSE on failure.
 */
function wpsqli_init()
{
    return mysqli_init();
}

/**
 * Opens a connection to a mysql server in a real context.
 *
 * This function is a wrapper for the mysqli_real_connect function, which attempts to establish
 * a connection to a MySQL server. The function takes in parameters for the host name, username,
 * password, database name, port number, socket, and flags, all of which are optional except the
 * mysqli object returned by mysqli_init(). The flags parameter can be used to set different
 * connection options that can affect the behavior of the connection.
 *
 * @param mysqli $connection The mysqli object returned by mysqli_init().
 * @param string|null $hostname The host name or an IP address.
 * @param string|null $username The MySQL user name.
 * @param string|null $password The password associated with the username.
 * @param string|null $database The default database to be used when performing queries.
 * @param int|null $port The port number to attempt to connect to the MySQL server.
 * @param string|null $socket The socket or named pipe that should be used.
 * @param int $flags Client connection flags.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_real_connect($connection, $hostname = null, $username = null, $password = null, $database = null, $port = null, $socket = null, $flags = 0)
{
    return mysqli_real_connect($connection, $hostname, $username, $password, $database, $port, $socket, $flags);
}

/**
 * Closes a previously opened database connection.
 *
 * This function is a wrapper for the mysqli_close function. It is used to close a non-persistent
 * connection to a MySQL server that was opened with mysqli_connect() or mysqli_real_connect(). It's
 * important to close connections when they are no longer needed to free up resources on both the web
 * server and the MySQL server. The function returns TRUE on success or FALSE on failure.
 *
 * @param mysqli $connection The mysqli connection resource to be closed.
 * @return bool Returns TRUE on successful closure, FALSE on failure.
 */
function wpsqli_close($connection)
{
    return mysqli_close($connection);
}

/**
 * Used to establish secure connections using SSL.
 *
 * This function is a wrapper for the mysqli_ssl_set function. It is used to set the SSL
 * certificates and key files for establishing an encrypted connection between the client
 * and the MySQL server. This function should be called before mysqli_real_connect(). It's
 * important for security when the database server and the web server are on different hosts
 * or when sensitive data is being transferred.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $key The path to the key file.
 * @param string $cert The path to the certificate file.
 * @param string $ca The path to the certificate authority file.
 * @param string $capath The pathname to a directory that contains trusted SSL CA certificates
 *                       in PEM format.
 * @param string $cipher A list of allowable ciphers to use for SSL encryption.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_ssl_set($connection, $key, $cert, $ca, $capath, $cipher)
{
    return mysqli_ssl_set($connection, $key, $cert, $ca, $capath, $cipher);
}

/**
 * Returns the MySQL client library version as a string.
 *
 * This function is a wrapper for the mysqli_get_client_info function. It is used to retrieve
 * the version of the client library that is used to compile the MySQLi extension. The function
 * does not require any parameters and can be called statically. It is helpful for debugging
 * and ensuring that the PHP environment is using the correct version of the MySQL client library,
 * which can be important for compatibility and functionality reasons.
 *
 * @return string The MySQL client library version.
 */
function wpsqli_get_client_info()
{
    return mysqli_get_client_info();
}

/**
 * Retrieves the version of the MySQL server.
 *
 * This function is a wrapper for the mysqli_get_server_info function. It returns a string
 * representing the version of the MySQL server pointed to by the connection resource. This
 * information can be used for a variety of purposes, such as conditional behavior for different
 * MySQL versions or simply for logging and monitoring. Understanding the server version is
 * essential for ensuring compatibility with specific MySQL features and syntax.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return string The version of the MySQL server.
 */
function wpsqli_get_server_info($connection)
{
    return mysqli_get_server_info($connection);
}

/**
 * Returns a string representing the type of connection used.
 *
 * This function is a wrapper for the mysqli_get_host_info function. It retrieves information about
 * the type of connection that was established to the MySQL server and the host server information.
 * This includes the host name and the connection type, such as TCP/IP or a UNIX socket. It's useful
 * for debugging and for understanding how PHP is communicating with the MySQL server.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return string A string describing the connection type and server host information.
 */
function wpsqli_host_info($connection)
{
    return mysqli_get_host_info($connection);
}

/**
 * Pings a server connection, or tries to reconnect if the connection has gone down.
 *
 * This function is a wrapper for the mysqli_ping function, which checks whether the
 * connection to the server is working. If it has gone down, and the global option
 * mysqli.reconnect is enabled, it will attempt to reconnect. This is useful to ensure
 * that a connection is still alive and if not, to re-establish it before proceeding
 * with further operations. It returns TRUE if the connection is alive or if it was
 * successfully re-established, and FALSE if the connection is not established and
 * cannot be re-established.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_ping($connection)
{
    return mysqli_ping($connection);
}

/**
 * Returns the thread ID for the current connection.
 *
 * This function is a wrapper for the mysqli_thread_id function. It retrieves the thread ID used by
 * the current connection to the MySQL server. This ID can be used as an argument to the KILL
 * statement to terminate a connection. It is useful for debugging and managing MySQL connections
 * and can be used to uniquely identify the connection within the server's process.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return int The thread ID for the current connection.
 */
function wpsqli_thread_id($connection)
{
    return mysqli_thread_id($connection);
}

/**
 * Returns whether the client library is thread-safe.
 *
 * This function is a wrapper for the mysqli_thread_safe function. It indicates whether the
 * mysqli client library that PHP is using is thread-safe. This is important information when
 * running PHP in a multi-threaded environment such as with the worker MPM in Apache or when
 * using multi-threading extensions in PHP.
 *
 * @return bool Returns TRUE if the client library is thread-safe, FALSE otherwise.
 */
function wpsqli_thread_safe()
{
    return mysqli_thread_safe();
}

/**
 * Gets the current system status of the MySQL server.
 *
 * This function is a wrapper for the mysqli_stat function. It returns a string containing
 * status information about the MySQL server to which it's connected. The information includes
 * uptime, threads, queries, open tables, and flush tables, among other status indicators.
 * This can be useful for monitoring the health and performance of the MySQL server, as well
 * as for debugging purposes.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return string A string describing the server status or FALSE on failure.
 */
function wpsqli_stat($connection)
{
    return mysqli_stat($connection);
}

/**
 * Sets extra connect options and affect behavior for a connection.
 *
 * This function is a wrapper for the mysqli_options function. It is used to set extra options
 * for a connection resource before establishing a connection using mysqli_real_connect(). These
 * options can be used to control various aspects of the connection's behavior. The function should
 * be called after mysqli_init() and before mysqli_real_connect(). It returns TRUE on success or
 * FALSE on failure.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param int $option The specific option that is to be set.
 * @param mixed $value The value for the specified option.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_options($connection, $option, $value)
{
    return mysqli_options($connection, $option, $value);
}

/**
 * Returns the error code from the last connection attempt.
 *
 * This function is a wrapper for the mysqli_connect_errno function. It returns the error code from
 * the last call to mysqli_connect() or mysqli_real_connect(). It is useful for error handling after
 * attempting to establish a connection to a MySQL server, allowing the script to respond appropriately
 * to specific error conditions. The function does not take any parameters and returns an integer error
 * code. If no error occurred during the last connection attempt, it will return zero.
 *
 * @return int The error code from the last connection attempt.
 */
function wpsqli_connect_errno()
{
    return mysqli_connect_errno();
}

/**
 * Returns a string description of the last connect error.
 *
 * This function is a wrapper for the mysqli_connect_error function. It provides a textual description
 * of the error from the last connection attempt made by mysqli_connect() or mysqli_real_connect().
 * Unlike mysqli_connect_errno(), which returns an error code, mysqli_connect_error() returns a string
 * describing the error. This is useful for error handling, providing more detailed context about
 * connection problems.
 *
 * @return string|null A string that describes the error from the last connection attempt, or NULL
 *                     if no error occurred.
 */
function wpsqli_connect_error()
{
    return mysqli_connect_error();
}

/**
* Transaction Handling
*/

/**
 * Turns on or off auto-commit mode on queries for the database connection.
 *
 * This function is a wrapper for the mysqli_autocommit function. When turned on, each query
 * that you execute will automatically commit to the database. When turned off, you will need to
 * manually commit transactions using mysqli_commit() or rollback using mysqli_rollback(). This
 * function is particularly useful for transactions that require multiple steps and you don't want
 * to commit until all steps are successful.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param bool $mode Whether to turn on auto-commit mode or not. Pass TRUE to turn on auto-commit
 *                   mode and FALSE to turn it off.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_autocommit($connection, $mode)
{
    return mysqli_autocommit($connection, $mode);
}

/**
 * Starts a new transaction.
 *
 * This function is a wrapper for the mysqli_begin_transaction function. It starts a new transaction
 * with the provided connection and with the specified flags. Transactions allow multiple changes to
 * be made to the database atomically - they will all be applied, or none will be, which can be controlled
 * by committing or rolling back the transaction. This function can also set a name for the transaction,
 * which can be used for savepoints.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param int $flags Optional flags for defining transaction characteristics. This should be a bitmask
 *                   of any of the MYSQLI_TRANS_START_* constants.
 * @param string|null $name Optional name for the transaction, used for savepoint names.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_begin_transaction($connection, $flags = 0, $name = null)
{
    return mysqli_begin_transaction($connection, $flags, $name);
}

/**
 * Commits the current transaction.
 *
 * This function is a wrapper for the mysqli_commit function. It is used to commit the current transaction
 * for the database connection. Committing a transaction means that all the operations performed since the
 * start of the transaction are permanently saved to the database. This function can also take optional flags
 * and a name, the latter being used if the commit should be associated with a named savepoint.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param int $flags Optional flags for the commit operation. It should be a bitmask of the MYSQLI_TRANS_COR_* constants.
 * @param string|null $name Optional name for the savepoint that should be committed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_commit($connection, $flags = 0, $name = null)
{
    return mysqli_commit($connection, $flags, $name);
}

/**
 * Rolls back the current transaction for the database connection.
 *
 * This function is a wrapper for the mysqli_rollback function. It rolls back the current transaction,
 * undoing all changes made to the database in the current transaction. This is an essential feature
 * for maintaining data integrity, especially in situations where a series of database operations need
 * to be treated as an atomic unit. The function can also accept optional flags and a name, which can be
 * used to rollback to a named savepoint within the transaction rather than rolling back the entire transaction.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param int $flags Optional flags that define how the rollback operation should be handled. It should be
 *                   a bitmask of the MYSQLI_TRANS_COR_* constants.
 * @param string|null $name Optional name of the savepoint to which the rollback operation should be directed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_rollback($connection, $flags = 0, $name = null)
{
    return mysqli_rollback($connection, $flags, $name);
}

/**
* Database Operations
*/

/**
 * Selects the default database for database queries.
 *
 * This function is a wrapper for the mysqli_select_db function, which is used to change the default
 * database for the connection. This is useful when performing multiple operations across different
 * databases without having to establish a new connection for each one. If the function succeeds,
 * it will return TRUE, indicating the database was successfully selected, or FALSE if it fails.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $database The name of the database to select.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_select_db($connection, $database)
{
    return mysqli_select_db($connection, $database);
}

/**
 * Performs a query against the database.
 *
 * This function is a wrapper for the mysqli_query function. The mysqli_query function performs
 * a query against the database and returns a result set for successful SELECT queries, or TRUE
 * for other successful DML queries such as INSERT, UPDATE, DELETE, etc. It can also be used
 * to execute multiple queries if the database server supports it. The function can return FALSE
 * on failure. The optional third parameter defines the result mode - whether to use a resultset
 * buffering (MYSQLI_STORE_RESULT) or not (MYSQLI_USE_RESULT).
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $query The SQL query to be executed.
 * @param int $result_mode The optional mode for storing result set.
 * @return mixed Returns a mysqli_result object for successful SELECT queries, TRUE for other
 *               successful queries, or FALSE on failure.
 */
function wpsqli_query($connection, $query, $result_mode = MYSQLI_STORE_RESULT)
{
    return mysqli_query($connection, $query, $result_mode);
}

/**
 * Executes one or multiple queries which are concatenated by a semicolon.
 *
 * This function is a wrapper for the mysqli_multi_query function. It allows execution of
 * multiple SQL statements sent to the MySQL server in a single call. This can be useful to
 * perform a batch of SQL operations such as an atomic transaction that should either complete
 * entirely or not at all. After calling this function, the results of the queries can be
 * processed using mysqli_store_result() and mysqli_next_result(). It is important to ensure
 * that any user input included in the queries is properly sanitized to avoid SQL injection.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $query The queries to execute, concatenated by semicolons.
 * @return bool Returns TRUE on success or FALSE on the first error that occurred.
 *              If the first query succeeds, the function will return TRUE even if
 *              a subsequent query fails.
 */
function wpsqli_multi_query($connection, $query)
{
    return mysqli_multi_query($connection, $query);
}

/**
 * Prepares an SQL statement for execution.
 *
 * This function is a wrapper for the mysqli_prepare function. It prepares the SQL statement
 * and returns a statement object used for further operations on the statement. The statement
 * preparation is used to efficiently execute repeated queries with high efficiency and to avoid
 * SQL injection vulnerabilities by separating the query structure from its data. It is especially
 * useful when the same statement is executed multiple times with different parameters.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $query The SQL query to prepare.
 * @return mysqli_stmt|false Returns a statement object on success or FALSE on failure.
 */
function wpsqli_prepare($connection, $query)
{
    return mysqli_prepare($connection, $query);
}

/**
 * Executes a prepared Query.
 *
 * This function is a wrapper for the mysqli_stmt_execute function. It is used to execute a statement
 * that was previously prepared using the mysqli_prepare function. The execution will take place with
 * the current bound parameters in the statement object. This is commonly used in database operations
 * to execute the same statement repeatedly with high efficiency and to mitigate the risk of SQL injection
 * by separating SQL logic from the data being input.
 *
 * @param mysqli_stmt $stmt The mysqli_stmt statement object.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_execute($stmt)
{
    return mysqli_stmt_execute($stmt);
}

/**
 * Binds variables to a prepared statement as parameters.
 *
 * This function is a wrapper for the mysqli_stmt_bind_param function. It binds variables to the
 * placeholders of a prepared statement, which is represented by the `$stmt` parameter. The `$types`
 * parameter is a string that contains one character for each variable in `$vars`, indicating the type
 * of the variable. The supported types are 'i' for integer, 'd' for double, 's' for string, and 'b' for
 * blob. By using this function, the values of the variables are bound to the statement as it is executed,
 * which can be used to safely execute the statement with user-supplied input.
 *
 * @param mysqli_stmt $stmt The prepared statement to which the variables are bound.
 * @param string $types A string that contains a type specification char for each variable in `$vars`.
 * @param mixed ...$vars The variables to bind to the prepared statement.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_bind_param($stmt, $types, ...$vars)
{
    return mysqli_stmt_bind_param($stmt, $types, ...$vars);
}

/**
 * Binds variables to a prepared statement for result storage.
 *
 * This function is a wrapper for the mysqli_stmt_bind_result function. It binds variables to the
 * prepared statement `$stmt` to store the result of the statement once it is executed. The bound
 * variables are passed by reference and will be set to the values of the corresponding columns in
 * the result set. This function is typically used in conjunction with mysqli_stmt_fetch(), which
 * will populate the variables with data from the next row in the result set each time it is called.
 *
 * @param mysqli_stmt $stmt The statement object that executed a query with a result set.
 * @param mixed &...$vars The variables to which the result set columns will be bound.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_bind_result($stmt, &...$vars)
{
    return mysqli_stmt_bind_result($stmt, ...$vars);
}

/**
 * Fetches results from a prepared statement into the bound variables.
 *
 * This function is a wrapper for the mysqli_stmt_fetch function. It is used to fetch the data
 * from the executed prepared statement into the variables that were bound using mysqli_stmt_bind_result().
 * The function will return TRUE for every row fetched successfully. When there are no more rows to fetch,
 * it will return NULL, and if there is an error it will return FALSE.
 *
 * @param mysqli_stmt $stmt The prepared statement object from which results are to be fetched.
 * @return bool|null Returns TRUE on success, NULL if there are no more rows to fetch, or FALSE on error.
 */
function wpsqli_stmt_fetch($stmt)
{
    return mysqli_stmt_fetch($stmt);
}

/**
 * Closes a prepared statement.
 *
 * This function is a wrapper for the mysqli_stmt_close function. It deallocates the statement
 * and cleans up the memory associated with the statement object. This is an important step in
 * resource management, as it frees up server resources and allows other statements to be executed.
 * It should always be called after all the results have been fetched and the statement is no longer needed.
 *
 * @param mysqli_stmt $stmt The prepared statement object to be closed.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_stmt_close($stmt)
{
    return mysqli_stmt_close($stmt);
}

/**
 * Returns a string description for the last statement error.
 *
 * This function is a wrapper for the mysqli_stmt_error function. It returns a string describing
 * the error for the most recent statement operation that generated an error. This is useful for
 * debugging and error handling in applications that use prepared statements to interact with the
 * MySQL database. It allows developers to output or log a descriptive error message when a MySQL
 * operation on a prepared statement fails.
 *
 * @param mysqli_stmt $stmt The mysqli_stmt statement object.
 * @return string A string that describes the error. An empty string if no error occurred.
 */
function wpsqli_stmt_error($stmt)
{
    return mysqli_stmt_error($stmt);
}

/**
 * Returns the error code for the most recent statement call.
 *
 * This function is a wrapper for the mysqli_stmt_errno function. It returns the error code from
 * the last operation performed on the specified statement. This is useful for error handling,
 * particularly in database operations where you need to react differently based on the specific
 * error that occurred. It can be used in conjunction with mysqli_stmt_error() to retrieve both
 * the error code and the error message for more detailed debugging and logging.
 *
 * @param mysqli_stmt $stmt The mysqli_stmt statement object.
 * @return int An error code value for the last error that occurred, or zero if no error occurred.
 */
function wpsqli_stmt_errno($stmt)
{
    return mysqli_stmt_errno($stmt);
}

/**
* Result Handling
*/

/**
 * Fetches a result row as an associative, a numeric array, or both.
 *
 * This function is a wrapper for the mysqli_fetch_array function, which is used to fetch a single
 * row of data from the result set obtained from executing a SELECT query. The data can be fetched
 * as an associative array, a numeric array, or both, depending on the `mode` specified. By default,
 * it fetches as both associative and numeric (MYSQLI_BOTH). Using MYSQLI_ASSOC will fetch as an
 * associative array, and MYSQLI_NUM will fetch as a numeric array. It returns NULL when there are
 * no more rows to fetch.
 *
 * @param mysqli_result $result The result set from a query as returned by mysqli_query.
 * @param int $mode The type of array that should be produced from the current row data.
 * @return array|null Returns an array of strings that corresponds to the fetched row or NULL if
 *                    there are no more rows in result set.
 */
function wpsqli_fetch_array($result, $mode = MYSQLI_BOTH)
{
    return mysqli_fetch_array($result, $mode);
}

/**
 * Fetches a result row as an object.
 *
 * This function is a wrapper for the mysqli_fetch_object function. It retrieves the current row
 * of a result set as an object where the attributes correspond to the fetched row's column names.
 * This function can instantiate an object of a specified class, and pass parameters to its constructor,
 * allowing for custom objects based on the rows of the result set. If no class is specified, it defaults
 * to a stdClass object. If the class does not exist or the specified class's constructor requires more
 * arguments than are given, an exception is thrown.
 *
 * @param mysqli_result $result The result set returned by mysqli_query, mysqli_store_result
 *                              or mysqli_use_result.
 * @param string $class The name of the class to instantiate, set the properties of which
 *                      correspond to the fetched row's column names.
 * @param array $constructor_args An optional array of parameters to pass to the constructor
 *                                for the class name defined by the class parameter.
 * @return object|false An instance of the specified class with property names that correspond
 *                      to the column names returned in the result set, or FALSE on failure.
 */
function wpsqli_fetch_object($result, $class = "stdClass", $constructor_args = [])
{
    return mysqli_fetch_object($result, $class, $constructor_args);
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
 * @param mysqli_result $result The result set returned by a query against the database.
 *
 * @return array|null Returns an enumerated array of strings representing the fetched row,
 * or NULL if there are no more rows in the result set.
 */
function wpsqli_fetch_row(mysqli_result $result): ?array
{
    return mysqli_fetch_row($result);
}

/**
 * Adjusts the result pointer to an arbitrary row in the result set represented by the
 * $result object. This function can be used in conjunction with mysqli_fetch_row(),
 * mysqli_fetch_assoc(), mysqli_fetch_array(), or mysqli_fetch_object() to navigate between
 * rows in result sets, especially when using buffered result sets.
 *
 * This is an important function for situations where you need to access a specific row
 * directly without iterating over all preceding rows, which can be useful for pagination
 * or when looking up specific rows by row number.
 *
 * @param mysqli_result $result The result set returned by a query against the database.
 * @param int $row_number The desired row number to seek to. Row numbers are zero-indexed.
 *
 * @return bool Returns TRUE on success or FALSE on failure. If the row number is out of range,
 * it returns FALSE.
 */
function wpsqli_data_seek(mysqli_result $result, int $row_number): bool
{
    return mysqli_data_seek($result, $row_number);
}

/**
 * Returns the next field in the result set.
 *
 * This function is a wrapper for the mysqli_fetch_field function, which retrieves information about
 * the next field in the result set represented by the `$result` parameter. It can be used in a loop
 * to obtain information about each field in the result set, such as name, table, max length, flags,
 * and type. This is useful for dynamically generating table structures or processing query results
 * when the structure of the result set is not known in advance or changes.
 *
 * @param mysqli_result $result The result set returned by mysqli_query, mysqli_store_result
 *                              or mysqli_use_result.
 * @return object An object which contains field definition information or FALSE if no field information
 *                is available.
 */
function wpsqli_fetch_field($result)
{
    return mysqli_fetch_field($result);
}

/**
 * Gets the number of fields in a result set.
 *
 * This function is a wrapper for the mysqli_num_fields function. It returns the number
 * of fields (columns) in a result set. This is particularly useful when you need to
 * dynamically process a query result without knowing the schema of the returned data,
 * as it allows the script to iterate over all fields in each row of the result set.
 *
 * @param mysqli_result $result The result set returned by mysqli_query, mysqli_store_result
 *                              or mysqli_use_result.
 * @return int The number of fields in the specified result set.
 */
function wpsqli_num_fields($result)
{
    return mysqli_num_fields($result);
}

/**
 * Returns the number of columns for the most recent query on the connection.
 *
 * This function is a wrapper for the mysqli_field_count function. It retrieves the number of
 * columns obtained from the most recent query executed on the given database connection. This
 * can be particularly useful when you need to know how many columns will be returned by a
 * SELECT statement before fetching data, which can help in dynamically processing result sets.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return int An integer representing the number of fields in the result set.
 */
function wpsqli_field_count($connection)
{
    return mysqli_field_count($connection);
}

/**
 * Transfers a result set from the last query.
 *
 * This function is a wrapper for the mysqli_store_result function. It is used to transfer the
 * result set from the last query executed on the given connection, which used the MYSQLI_STORE_RESULT
 * flag. This function must be called after executing a query that returns a result set (like SELECT).
 * It allows the complete result set to be transferred to the client and then utilized via the
 * mysqli_fetch_* functions. It's particularly useful when the result set is expected to be accessed
 * multiple times.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return mysqli_result|false A buffered result object or FALSE if an error occurred.
 */
function wpsqli_store_result($connection)
{
    return mysqli_store_result($connection);
}

/**
 * Initiates the retrieval of a result set from the last query executed using the MYSQLI_USE_RESULT mode.
 *
 * This function is a wrapper for the mysqli_use_result function. It is used to initiate the retrieval
 * of a result set from the last query executed on the given connection, without storing the entire result
 * set in the buffer. This is particularly useful for handling large result sets that could potentially
 * exceed the available PHP memory. The data is fetched row-by-row, reducing the immediate memory footprint.
 * However, it requires the connection to remain open, and no other operations can be performed on the
 * connection until the result set is fully processed.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return mysqli_result|false An unbuffered result object or FALSE if an error occurred.
 */
function wpsqli_use_result($connection)
{
    return mysqli_use_result($connection);
}

/**
 * Frees the memory associated with a result.
 *
 * This function is a wrapper for the mysqli_free_result function. It's used to free the memory
 * allocated for a result set obtained from a query. When the result data is not needed anymore,
 * it's a good practice to free the associated resources, especially when dealing with large
 * datasets that can consume significant amounts of memory. It is an important aspect of resource
 * management and helps to keep the application's memory footprint minimal.
 *
 * @param mysqli_result $result The result set returned by mysqli_query, mysqli_store_result
 *                              or mysqli_use_result.
 * @return void This function doesn't return any value.
 */
function wpsqli_free_result($result)
{
    return mysqli_free_result($result);
}

/**
 * Checks if there are any more result sets from a multi query.
 *
 * This function is a wrapper for the mysqli_more_results function. It is used after executing
 * a multi query with mysqli_multi_query() to check if there are more result sets available.
 * This is important when processing multiple SQL statements in one call, as it determines
 * whether the application should keep reading results before sending more statements to the server.
 * It returns TRUE if one or more result sets are available from the previous calls to
 * mysqli_multi_query(), otherwise FALSE.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return bool Returns TRUE if there are more result sets from previous multi queries and
 *              FALSE otherwise.
 */
function wpsqli_more_results($connection)
{
    return mysqli_more_results($connection);
}

/**
 * Moves the internal result pointer to the next result set returned from a multi query.
 *
 * This function is a wrapper for the mysqli_next_result function, which is used in a multi query
 * scenario. After executing mysqli_multi_query(), which can send multiple SQL statements to the
 * server at once, mysqli_next_result() checks for more result sets and prepares the next one for
 * reading. This is crucial for handling multiple operations executed with mysqli_multi_query() to
 * ensure that all result sets are processed sequentially. It returns TRUE if there is another result set,
 * FALSE if there are no more result sets, or FALSE with an error if there is a problem moving the result pointer.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return bool Returns TRUE on success or FALSE on failure (no more results or an error occurred).
 */
function wpsqli_next_result($connection)
{
    return mysqli_next_result($connection);
}

/**
* Utility Functions
*/

function wpsqli_is_resource($object)
{
    return $object !== false && $object !== null;
}

/**
 * Gets the number of affected rows in the previous MySQL operation.
 *
 * This function is a wrapper for the mysqli_affected_rows function, which is used to determine
 * the number of rows affected by the last INSERT, UPDATE, REPLACE or DELETE query executed on
 * the given connection. It is an important function for understanding the impact of such queries,
 * allowing the developer to verify that the expected number of rows were altered. It returns an
 * integer indicating the number of rows affected or -1 if the last query failed.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return int The number of affected rows in the previous operation, or -1 if the last operation failed.
 */
function wpsqli_affected_rows($connection)
{
    return mysqli_affected_rows($connection);
}

/**
 * Retrieves the ID generated by a query on a table with a column having the AUTO_INCREMENT attribute.
 *
 * This function is a wrapper for the mysqli_insert_id function, which returns the auto generated id
 * used in the last query. It is typically used after an INSERT query into a table with an auto-increment
 * field. The id returned is the one that was automatically generated for the AUTO_INCREMENT column in
 * the affected table. If the last query wasn't an INSERT or UPDATE statement or didn't affect an
 * AUTO_INCREMENT column, or if the AUTO_INCREMENT value was set to a non-positive value manually,
 * this function will return zero.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return int|string The ID generated for an AUTO_INCREMENT column by the previous query on success, 0 if the previous
 *                    query does not generate an AUTO_INCREMENT value, or FALSE on failure.
 */
function wpsqli_insert_id($connection)
{
    return mysqli_insert_id($connection);
}


/**
 * Sets the default character set to be used when sending data from and to the database server.
 *
 * This function is a wrapper for the mysqli_set_charset function. The mysqli_set_charset function
 * is used to set the character set to be used when sending data from and to the database server.
 * This is particularly important to ensure that data is properly encoded and decoded when stored
 * and retrieved from the database, avoiding character encoding issues.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $charset The desired character set.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_set_charset($connection, $charset)
{
    return mysqli_set_charset($connection, $charset);
}

/**
 * Escapes special characters in a string for use in an SQL statement.
 *
 * This function serves as a wrapper for the mysqli_real_escape_string function, which is
 * utilized to escape potentially dangerous special characters within a string. This is a
 * critical security measure to prevent SQL injection vulnerabilities by ensuring that user
 * input can be safely used in an SQL query. The function takes a string to be escaped and
 * the mysqli connection resource, and returns the escaped string which is safe to be included
 * in SQL statements.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @param string $string The string to be escaped.
 * @return string Returns the escaped string.
 */
function wpsqli_real_escape_string($connection, $string)
{
    return mysqli_real_escape_string($connection, $string);
}

/**
 * Retrieves the last error description for the most recent MySQLi function call
 * that can succeed or fail.
 *
 * This function is a wrapper for the mysqli_error function, which returns a string
 * describing the error from the last MySQL operation associated with the provided
 * connection resource. It's an essential function for debugging and error handling
 * in MySQL-related operations. When a MySQLi function fails, wpsqli_error can be
 * used to fetch the corresponding error message to understand what went wrong.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return string Returns a string with the error message for the most recent function call
 *                if it has failed, or an empty string if no error has occurred.
 */
function wpsqli_error($connection)
{
    return mysqli_error($connection);
}

/**
 * Retrieves the error code for the most recent function call that failed.
 *
 * This function is a wrapper for the mysqli_errno function. It returns the error code from
 * the last error that occurred during a MySQL operation on the given connection. This code
 * can be used in conjunction with mysqli_error() to provide a detailed explanation of the
 * error, especially for logging or for generating user-friendly error messages.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return int Returns an error code value representing the error from the last MySQL operation
 *             on the given connection, or zero if no error occurred.
 */
function wpsqli_errno($connection)
{
    return mysqli_errno($connection);
}

/**
 * Enables or disables internal report functions.
 *
 * This function is a wrapper for the mysqli_report function, which is used to set the
 * reporting mode of mysqli errors. This is useful for defining whether errors should be
 * reported as exceptions, warnings, or silent (no report). It's important for configuring
 * the error reporting behavior of the mysqli extension to suit the needs of your application,
 * particularly in a development environment where more verbose error reporting is beneficial.
 *
 * @param int $flags A bit-mask constructed from the MYSQLI_REPORT_* constants.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function wpsqli_report($flags)
{
    return mysqli_report($flags);
}

/**
 * Retrieves information about the most recently executed query.
 *
 * This function is a wrapper for the mysqli_info function. It provides a string containing
 * information about the most recently executed query on the given connection resource. This
 * can include information such as the number of rows affected by an INSERT, UPDATE, REPLACE,
 * or DELETE query, as well as the number of rows matched and changed. It is valuable for
 * obtaining detailed insights into the execution of database operations.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return string|null A string representing information about the last query executed,
 *                     or NULL if no information is available.
 */
function wpsqli_info($connection)
{
    return mysqli_info($connection);
}

/**
 * Initializes a statement and returns an object for use with mysqli_stmt_prepare.
 *
 * This function is a wrapper for the mysqli_stmt_init function. It creates and returns a new statement
 * object associated with the specified database connection. This statement object can then be used
 * to prepare a SQL statement for execution. It's particularly useful when you need to execute a
 * prepared statement multiple times with different parameters, providing benefits such as improved
 * query performance and protection against SQL injection attacks.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return mysqli_stmt A new statement object or FALSE on failure.
 */
function wpsqli_stmt_init($connection)
{
    return mysqli_stmt_init($connection);
}

/**
 * Polls connections for results.
 *
 * This function is a wrapper for the mysqli_poll function. It can be used to poll multiple
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
    return mysqli_poll(...$args);
}

/**
 * Gets the result from asynchronous MySQL query.
 *
 * This function is a wrapper for the mysqli_reap_async_query function. It is used after initiating
 * a query with mysqli_query() on a connection with the MYSQLI_ASYNC flag set. It retrieves the result
 * from the query once it is complete, which can be used with mysqli_poll() to manage multiple
 * asynchronous queries. It returns a mysqli_result object for successful SELECT queries, or TRUE for
 * other DML queries (INSERT, UPDATE, DELETE, etc.) if the operation was successful, or FALSE on failure.
 *
 * @param mysqli $connection The mysqli connection resource.
 * @return mysqli_result|bool A mysqli_result object for successful SELECT queries, TRUE for other
 *                            successful DML queries, or FALSE on failure.
 */
function wpsqli_reap_async_query($connection)
{
    return mysqli_reap_async_query($connection);
}
