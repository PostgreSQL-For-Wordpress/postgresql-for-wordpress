=== PostgreSQL for WordPress (PG4WP) ===
Contributors: Hawk__ (http://www.hawkix.net/)
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FPT8RPZGFX7GU
Tags: database, postgresql, PostgreSQL, postgres, mysql
Requires at least: 2.9.2
Tested up to: 4.5.3
Stable tag: 1.3.1
License: GPLv2 or later

PostgreSQL for WordPress is a special 'plugin' enabling WordPress to be used with a PostgreSQL database.

== Description ==

PostgreSQL for WordPress (PG4WP) gives you the possibility to install and use WordPress with a PostgreSQL database as a backend.
It works by replacing calls to MySQL specific functions with generic calls that maps them to another database functions and rewriting SQL queries on the fly when needed.

Currently, support is focused on PostgreSQL, but other databases can be added quite easily by providing the appropriate 'driver'.
MySQL driver is enclosed, which just does "nothing".
If you need/wish support for another database, please feel free to contact the author, writing a driver is not really hard if you know a bit about SQL and the database you want support for.

If you want to use this plugin, you should be aware of the following :
- WordPress with PG4WP is expected to be slower than the original WordPress with MySQL because PG4WP does much SQL rewriting for any page view
- Some WordPress plugins should work 'out of the box' but many plugins won't because they would need specific code in PG4WP

You shouldn't expect any plugin specific code to be integrated into PG4WP except for plugins shipped with WordPress itself (such as Akismet).
PG4WP 2.0 will have a mechanism to add plugin support.

== Installation ==

You have to install PG4WP *before* configuring your WordPress installation for things to work properly. 
This is because the database needs to be up and running before any plugin can be loaded.

1.  Place your WordPress files in the right place on your web server.

1.	Unzip the files from PG4WP and put the `pg4wp` directory in your `/wp-content` directory.

1.	Copy the `db.php` from the `pg4wp` directory to `wp-content`
	
	You can modify this file to configure the database driver you wish to use
	Currently you can set 'DB_DRIVER' to 'pgsql' or 'mysql'
	
	You can also activate DEBUG and/or ERROR logs

1.	Create `wp-config.php` from `wp-config-sample.php` if it does not already exist (PG4WP does not currently intercept database connection setup).

1.	Point your Web Browser to your WordPress installation and go through the traditional WordPress installation routine.

== Frequently Asked Questions ==

= I have an error adding a new category =

You should try to run `SELECT setval('wp_terms_seq', (SELECT MAX(term_id) FROM wp_terms)+1);` to correct the sequence number for the `wp_terms` table.

Note : you should replace wp_ with the appropriate table prefix if you changed it in your WordPress installation

= Does plugin `put any plugin name here` work with PG4WP ? =

There is no simple answer to this question.
Plugins not doing any database calls will certainly work.

Database-intensive plugins may work, but most of them would require specific code in PG4WP to work.

You should backup your setup (at least database) and try to install the plugin to see if it works or not.
Whether it worked or not, you should tell me the result of your test, so that I can create some kind of listing of working/not working plugins.

== Screenshots ==
There is no screenshot for this plugin

== Changelog ==

= 1.3.1 =
* Integrated changes pointed in http://vitoriodelage.wordpress.com/2014/06/06/add-missing-wpsql_errno-in-pg4wp-plugin/ to correct problems with WP 3.9.1

= 1.3.0 =
* Some cleanup in old code that is not needed anymore
* Enhanced wordpress-importer compatibility
* Optimizations in wpsql_insert_id()

= 1.3.0b1 =
* Added support for PostgreSQL 9.1+ (doesn't break compatibility with older versions)
* Added support for specifying port in the server host (eg 'localhost:3128') (Patch from convict)
* Added a handle for converting CAST(... AS CHAR) to CAST(... AS TEXT) (Problem pointed out by Aart Jan)
* Added a filter to remove 'IF NOT EXISTS' for 'CREATE TABLE' queries
* Enhancements for WPMU support

= 1.2.2 =
* Corrected SQL_CALC_FOUND_ROWS handling, was broken by the latest code reorganisation

= 1.2.1 =
* Corrected 'ON DUPLICATE KEY ...' handling (was not working at all)
* Modified SQL_CALC_FOUND_ROWS handling for correct paging
* Some conversion handling for WPMU to install correctly (WPMU not working yet though)
* Improved installation/upgrade handling code (better detection of indexes, ADD COLUMN support, ...)

= 1.2.0 =
* Error logging is disabled in the distribution
* Added a handle for correct counting of users and roles
* Added MONTH and YEAR to the 'INTERVAL...' handling code
* Removed all ZdMultilang support hacks

= 1.2.0rc =
* Disabled all ZdMultilang support hacks
* Fixed regressions that caused some Wordpress features to not work properly
* Rewrote database connection handling so Wordpress installation can tell you when your username and password are wrong
* Support for using an empty password for database connection
	Note : this requires setting 'PG4WP_INSECURE' to true in `db.php` for PG4WP to accept this
* Some code optimizations

= 1.2.0b1 =
* Somewhat improved Wordpress plugins compatibility
* Added 'PG4WP_INSECURE' parameter for future use
* Split 'db.php' to be just some kind of loader for PG4WP to ease upgrading
* Improved Akismet compatibility
* Upgrading works with minor errors (PostgreSQL complains about already existing relations)
	Tested successfully : 2.9.2 to 3.0.6 - 2.9.2 to 3.1.4 - 2.9.2 to 3.2.1
* Support for Wordpress up to 3.2.1 (Installing WP 2.9.2, 3.0.6, 3.1.4 and 3.2.1 works smoothly)
* Implemented a generic "INTERVAL xx DAY|HOUR|MINUTE|SECOND" handler
* Backticks and capital text containing 'ID' now work 
* Improved db.php to remove notices and possible fatal errors
* Improved dates functions handling
* PG4WP now appears in WordPress control panel and can be enabled/disabled but this has no real effect
* Added a correct plugin header into db.php to have correct informations shown in WordPress plugin Directory

= 1.1.0 =
* This release is identical to 1.1.0rc4, just has error logging deactivated in the distribution

= 1.1.0rc4 =
* Corrected a typo in permalinks handling

= 1.1.0rc3 =
* Reordered the date_funcs array (Thanks to Boris HUISGEN for reporting the problem and submitting a patch)
* Moved the hack about WP using meta_value = integer (instead of text) out of the SELECT handler
* Boris HUISGEN submitted a patch for permalinks to work properly

= 1.1.0rc =
* Hack for WP using meta_value = integer (instead of text)
* Moved parts required only when installing/upgrading from driver_pgsql.php to a separate file
	The file is loaded only when needed so that memory footprint should be a bit smaller
* Added UNIX_TIMESTAMP support
* Added DATE_SUB support for Akismet 2.2.7
* Added DAYOFMONTH support (Thanks to Pete Deffendol for noticing the problem)
* Upgrading from WP 2.8.6 to WP 2.9.1 works with a minor error
	Upgrading should remove an index on table "wp_options" that may not exist, throwing an error
* Installing WP 2.9.1 works smoothly
* Generic hack to avoid duplicate index names
* REGEXP gets replaced with '~'
* Added a hack to handle "ON DUPLICATE KEY" 
* Moved handling field names with CAPITALS near the end
* Added support for "INTERVAL 15 DAY" found in Akismet 2.2.7

= 1.0.2 =
* Updated support for plugin zdMultilang 1.2.5
* Got rid of some remaining hardcoded table prefix
* Added the possibility to log only errors

= 1.0.1 =
* Reorganisation of directory structure
* Updated installation procedure
* Changed the fake server version to 4.1.3
* Added support for Unix socket connections (just leave the "host" field empty when installing)

= 1.0.0 =
* Initial stable release.
* Code optimisation and reorganisation.
* `db.php` automatically rewrites `wp-db.php` when loading it, so no maintenance is needed anymore
	It also reduces the size of the archive :)
* Debug logs are now written in the `pg4wp` directory
* Renamed the driver files

= 0.9.11 =
* MySQL's DESCRIBE emulation ( for WordPress upgrade process )
* MySQL's SHOW INDEX emulation  ( for WordPress upgrade process )
* ALTER TABLE support ( for WordPress upgrade process )
* Added INDEX creation support when installing
* Cleaned type conversion Array
* Some code optimizations and cleanup
* One debug log file for each query type
* Tested successfully with WP 2.7.1 (Installs with no error + Upgrade to 2.8 OK with non blocking errors)
* Tested successfully with WP 2.6.5 (Installs with no error + Upgrade to 2.8 OK with non blocking errors)
* Tested successfully with WP 2.5.1 (Installs with no error + Upgrade to 2.8 OK)

= 0.9.10 =
* Ignore errors about non existing table "wp_options" while installing WordPress when debugging is on
* SQL_CALC_FOUND_ROWS emulation, to have correct posts paging
* Introduced support for the ZdMultiLang plugin

= 0.9.9 =
* Comments deletion now works again
* Most specific global variables renamed to have 'pg4wp' in their name

= 0.9.8 =
* Case insensitivity of MySQL 'LIKE' restored
* Importing WordPress eXtended RSS tested and seems to work

== Upgrade Notice ==

= 1.2.0 =
This version provides support for Wordpress up to 3.2.1
Upgrading to this version requires you to replace your existing `dp.php` with the one from the `pg4wp` directory.
Note : since 1.2.0b1, it is recommended to put the `pg4wp` directory directly in `/wp-content`

= 1.0 =
Initial stable release, you should upgrade to this version if you have installed any older release

== License ==
PG4WP is provided "as-is" with no warranty in the hope it can be useful.

PG4WP is licensed under the [GNU GPL](http://www.gnu.org/licenses/gpl.html "GNU GPL") v2 or any newer version at your choice.
