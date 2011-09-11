=== PostgreSQL for WordPress (PG4WP) ===
Contributors: Hawk__ (http://www.hawkix.net/)
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FPT8RPZGFX7GU
Tags: database, postgresql, PostgreSQL, postgres, mysql
Requires at least: 2.5.1
Tested up to: 3.2.1
Stable tag: 1.2.2

PostgreSQL for WordPress is a special 'plugin' enabling WordPress to be used with a PostgreSQL database.

== Description ==

PostgreSQL for WordPress (PG4WP) gives you the possibility to install and use WordPress with a PostgreSQL database as a backend.
It works by replacing calls to MySQL specific functions with generic calls that maps them to another database functions and rewriting SQL queries on the fly when needed.

Currently, support is focused on PostgreSQL, but other databases can be added quite easily by providing the appropriate 'driver'.
MySQL driver is enclosed, which just does "nothing".
If you need/wish support for another database, please feel free to contact the author, writing a driver is not really hard if you know a bit about SQL and the database you want support for.

== Installation ==

You have to install PG4WP *before* configuring your WordPress installation for things to work properly. 
This is because the database needs to be up and running before any plugin can be loaded.

1.  Place your WordPress files in the right place on your web server.

1.	Unzip the files from PG4WP and put the `pg4wp` directory in your `/wp-content` directory.

1.	Copy the `dp.php` from the `pg4wp` directory to `wp-content`
	
	You can modify this file to configure the database driver you wish to use
	Currently you can set 'DB_DRIVER' to 'pgsql' or 'mysql'
	
	You can also activate DEBUG and/or ERROR logs

1.	Point your Web Browser to your WordPress installation and go through the traditional WordPress installation routine.

== Frequently Asked Questions ==
No question yet, please contact me if you have any.

== Screenshots ==
There is no screenshot for this plugin

== Changelog ==

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
* Rewrote database connection handling so Wordpress installation can tell you when you username and password are wrong
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

== Licence ==
PG4WP is provided "as-is" with no warranty in the hope it can be useful.

PG4WP is licensed under the [GNU GPL](http://www.gnu.org/licenses/gpl.html "GNU GPL") v2 or any newer version at your choice.
