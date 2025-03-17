## PostgreSQL for WordPress (PG4WP) 

### Description 

PostgreSQL for WordPress (PG4WP) gives you the possibility to install and use WordPress with a PostgreSQL database as a backend.

#### Use Cases

- Run Wordpress on your Existing Postgres Cluster
- Run Wordpress with Georeplication with a Multi-Active Postgres instalation such as [EDB Postgres Distributed](https://www.enterprisedb.com/products/edb-postgres-distributed), or [CockroachDB](https://www.cockroachlabs.com/serverless/) for a [highly available](https://www.cockroachlabs.com/blog/brief-history-high-availability/) and resilient Wordpress Infrastructure

### Design

PostgreSQL for Wordpress works by intercepting calls to the mysqli_ driver in wordpress's wpdb class.
it replaces calls to mysqli_ with wpsqli_ which then are implemented by the driver files found in this plugin. 

![PG4WP Design](docs/images/pg4wp_design.png)

### Supported Wordpress Versions

This plugin has been tested against 
- Wordpress 6.5.3, 6.4.3 (v3 branch)
- Wordpress 6.3.2 (v2 branch)

### Supported PHP versions

This plugin requires PHP 8.1 or greater

### Supported PostgreSQL versions

This plugin has been tested on PostgreSQL 14.2

### Plugin Support

| Plugin                 | Version     | Working   |
| -----------            | ----------- | --------- |
| Debug Bar              | 1.1.4       | Confirmed |
| Yoast Duplicate Post   | 4.2         | Confirmed |

### Theme Support

| Theme                 | Version     | Working   |
| -----------           | ----------- | --------- |
| Twenty Twenty-Four    | 1.0         | Confirmed |
| Twenty Twenty-Three   | 1.2         | Confirmed |
| Twenty Twenty-Two     | 1.5         | Confirmed |
| Twenty Twenty-One     | 1.9         | Confirmed |

### Installation

You have to install PG4WP *before* configuring your WordPress installation for things to work properly. 
This is because the database needs to be up and running before any plugin can be loaded.

1.  Place your WordPress files in the right place on your web server.

1.  Download the latest release From the [releases page](https://github.com/PostgreSQL-For-Wordpress/postgresql-for-wordpress/releases)

1.	Unzip the files from PG4WP and put the `pg4wp` directory in your `/wp-content` directory.

1.	Copy the `db.php` from the `pg4wp` directory to `wp-content`
	
	You can modify this file to configure the database driver you wish to use
	Currently you can set 'DB_DRIVER' to 'pgsql' or 'mysql'
	
	You can also activate DEBUG and/or ERROR logs

1.	Create `wp-config.php` from `wp-config-sample.php` if it does not already exist (PG4WP does not currently intercept database connection setup).

1.	Point your Web Browser to your WordPress installation and go through the traditional WordPress installation routine.


### Contributing

Contributions are welcome, please open a pull request with your changes and make sure the tests pass by running the test suite using
`./tests/tools/phpunit.phar tests/`

If you find a failing scenario please add a test for it, A PR which fixes a scenario but does not include a test will not be accepted. 

### License
PG4WP is provided "as-is" with no warranty in the hope it can be useful.

PG4WP is licensed under the [GNU GPL](http://www.gnu.org/licenses/gpl.html "GNU GPL") v2 or any newer version at your choice.

### Changelog

#### Latest Changes
- Fixed issue with SQL DELETE query rewriting to use DB_PREFIX consistently, which previously caused PostgreSQL syntax errors due to hardcoded table prefixes

### Contributors
Code originally by Hawk__ (http://www.hawkix.net/)
Modifications by @kevinoid and @mattbucci
