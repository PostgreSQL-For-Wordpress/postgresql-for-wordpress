<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

// You can choose the driver to load here
define('DB_DRIVER', 'pgsql'); // 'pgsql' or 'mysql' are supported for now

// Set this to 'true' and check that `pg4wp` is writable if you want debug logs to be written
define( 'PG4WP_DEBUG', false);
// Logs are put in the pg4wp directory
define( 'PG4WP_LOG', dirname( __FILE__).'/pg4wp/');

// Load the driver defined above
require_once( dirname( __FILE__).'/pg4wp/driver_'.DB_DRIVER.'.php');

// This loads up the wpdb class applying the appropriate changes to it, DON'T TOUCH !
$replaces = array(
	'mysql_'	=> 'wpsql_',
	'<?php'		=> '',
	'?>'		=> '',
);
eval( str_replace( array_keys($replaces), array_values($replaces), file_get_contents(ABSPATH.'/wp-includes/wp-db.php')));
