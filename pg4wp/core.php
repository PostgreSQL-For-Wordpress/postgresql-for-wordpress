<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

/**
* This file does all the initialisation tasks
*/

// This is required by class-wpdb so we must load it first
require_once ABSPATH . '/wp-includes/version.php';
require_once ABSPATH . '/wp-includes/cache.php';
require_once ABSPATH . '/wp-includes/l10n.php';

// Load the driver defined in 'db.php'
require_once(PG4WP_ROOT . '/driver_' . DB_DRIVER . '.php');

// This loads up the wpdb class applying appropriate changes to it
$replaces = array(
    'define( '	=> '// define( ',
    'class wpdb'	=> 'class wpdb2',
    'new wpdb'	=> 'new wpdb2',
    'instanceof mysqli_result' => 'instanceof \PgSql\Result',
    'instanceof mysqli' => 'instanceof \PgSql\Connection',
    '$this->dbh->connect_errno' => 'wpsqli_connect_error()',
    'mysqli_'	=> 'wpsqli_',
    'is_resource'	=> 'wpsqli_is_resource',
    '<?php'		=> '',
    '?>'		=> '',
);

eval(str_replace(array_keys($replaces), array_values($replaces), file_get_contents(ABSPATH . '/wp-includes/class-wpdb.php')));

// Create wpdb object if not already done
if (!isset($wpdb) && defined('DB_USER')) {
    $wpdb = new wpdb2(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}
