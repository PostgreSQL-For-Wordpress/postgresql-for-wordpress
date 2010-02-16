<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

// You can choose the driver to load here
define('DB_DRIVER', 'pgsql'); // 'pgsql' or 'mysql' are supported for now

// This defines the directory where PG4WP files are
define( 'PG4WP_ROOT', dirname( __FILE__).'/plugins/pg4wp');

// Set this to 'true' and check that `pg4wp` is writable if you want debug logs to be written
define( 'PG4WP_DEBUG', false);
// If you just want to log queries that generate errors, leave PG4WP_DEBUG to "false"
// and set this to true 
define( 'PG4WP_LOG_ERRORS', false);

// Logs are put in the pg4wp directory
define( 'PG4WP_LOG', PG4WP_ROOT.'/logs/');
// Check if the logs directory is needed and exists or create it if possible
if( (PG4WP_DEBUG || PG4WP_LOG_ERRORS) &&
	!file_exists( PG4WP_LOG) &&
	is_writable(dirname( PG4WP_LOG)))
	mkdir( PG4WP_LOG);

// Load the driver defined above
require_once( PG4WP_ROOT.'/driver_'.DB_DRIVER.'.php');

// This loads up the wpdb class applying the appropriate changes to it, DON'T TOUCH !
$replaces = array(
	'mysql_'	=> 'wpsql_',
	'<?php'		=> '',
	'?>'		=> '',
);
eval( str_replace( array_keys($replaces), array_values($replaces), file_get_contents(ABSPATH.'/wp-includes/wp-db.php')));
