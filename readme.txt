=== PostgreSQL for WordPress (PG4WP) ===
Contributors: Hawk__ (http://www.hawkix.net/)
Donate link: http://www.hawkix.net/faire-un-don/
Tags: database, postgresql, PostgreSQL, postgres, mysql
Requires at least: 2.5.1
Tested up to: 2.8.2
Stable tag: 1.0.0

PostgreSQL for WordPress is a special 'plugin' enabling WordPress to be used with a PostgreSQL database.

== Description ==

Wordpress has always been locked into using mysql as its database storage engine.  There is a good discussion of 'why' [in the codex](http://codex.wordpress.org/Using_Alternative_Databases#Solutions/ "Codex Discussion")

But some people would like to use some other databases such as PostgreSQL. There are many diffrent motivations behind this, sometimes people already have PostgreSQL on their server and don't want to install MySQL along PostgreSQL, or simply don't like MySQL and prefer using alternatives.

PostgreSQL for WordPress (PG4WP) gives you the possibility tu install and use WordPress with a PostgreSQL database as a backend.
It works by replacing calls to MySQL specific functions with generic calls that maps them to another database functions.

When needed, the original SQL queries are rewritten on the fly so that MySQL specific queries work fine with the backend database. 

Currently, support is focused on PostgreSQL, but other databases can be added quite easily by providing the appropriate 'driver'.
MySQL driver is enclosed, which just does "nothing".
If you need/wish support for another database, please feel free to contact the author, writing a driver is not really hard if you know a bit about SQL and the database you want support for.

== Installation ==

This plugin can't be enabled through the control panel.
You have to install it before setting up your WordPress installation for things to work properly. 

This section describes how to install the plugin and get it working.
This is because the database needs to be up and running before any plugin can be loaded.

1.	Unzip the files in your `/wp-content` directory.
	After unzipping, the directory structure should look like this :
	
	`wp-content`
	`->plugins`
	`->themes`
	`->pg4wp`
	`db.php`
	`[maybe other files/directories]`
	
	The key is the presence of `pg4wp/` and `db.php` in `wp-content`

1.	Edit `db.php` to configure the database driver you wish to use
	
	Currently you can set 'DB_TYPE' to 'pgsql' or 'mysql'
	
	You can also activate DEBUG logs

1.	Point your Web Browser to your wordpress installation and go through the traditional WordPress installation routine.

== Frequently Asked Questions ==
No question yet, please contact me if you have any.

== Screenshots ==
There is no screenshot for this plugin

== Changelog ==

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

== Licence ==
PG4WP is provided "as-is" with no warranty.

PG4WP is licensed under the [GNU GPL](http://www.gnu.org/licenses/gpl.html "GNU GPL") v2 or any newer version at your choice.
