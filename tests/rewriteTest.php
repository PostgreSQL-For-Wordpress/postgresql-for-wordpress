<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . "/../");
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

require_once __DIR__ . "/../pg4wp/db.php";

final class rewriteTest extends TestCase
{
    public function test_it_can_rewrite_users_admin_query()
    {

        $sql = 'SELECT COUNT(NULLIF(`meta_value` LIKE \'%"administrator"%\', false)), COUNT(NULLIF(`meta_value` = \'a:0:{}\', false)), COUNT(*) FROM wp_usermeta INNER JOIN wp_users ON user_id = ID WHERE meta_key = \'wp_capabilities\'';
        $expected = 'SELECT COUNT(NULLIF(meta_value ILIKE \'%"administrator"%\', false)) AS count0, COUNT(NULLIF(meta_value = \'a:0:{}\', false)) AS count1, COUNT(*) FROM wp_usermeta INNER JOIN wp_users ON user_id = "ID" WHERE meta_key = \'wp_capabilities\'';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame($postgresql, $expected);
    }


    public function test_it_adds_group_by()
    {

        $sql = 'SELECT COUNT(id), username FROM users';
        $expected = 'SELECT COUNT(id) AS count0, username FROM users GROUP BY username';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame($postgresql, $expected);
    }

    public function test_it_handles_auto_increment() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_itsec_lockouts (
                lockout_id bigint UNSIGNED NOT NULL AUTO_INCREMENT, 
                lockout_type varchar(25) NOT NULL, 
                lockout_start timestamp NOT NULL, 
                lockout_start_gmt timestamp NOT NULL, 
                lockout_expire timestamp NOT NULL, 
                lockout_expire_gmt timestamp NOT NULL, 
                lockout_host varchar(40), 
                lockout_user bigint UNSIGNED, 
                lockout_username varchar(60), 
                lockout_active int(1) NOT NULL DEFAULT 1, 
                lockout_context TEXT, 
                PRIMARY KEY (lockout_id)
            )
        SQL;
        
        $expected = <<<SQL
            CREATE TABLE wp_itsec_lockouts (
                lockout_id bigserial, 
                lockout_type varchar(25) NOT NULL, 
                lockout_start timestamp NOT NULL, 
                lockout_start_gmt timestamp NOT NULL, 
                lockout_expire timestamp NOT NULL, 
                lockout_expire_gmt timestamp NOT NULL, 
                lockout_host varchar(40), 
                lockout_user bigint , 
                lockout_username varchar(60), 
                lockout_active smallint NOT NULL DEFAULT 1, 
                lockout_context TEXT, 
                PRIMARY KEY (lockout_id)
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($postgresql), trim($expected));
    }

    public function test_it_handles_auto_increment_without_null() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_e_events (
                    id bigint auto_increment primary key,
                    event_data text null,
                    created_at timestamp not null
            )
        SQL;
        
        $expected = <<<SQL
            CREATE TABLE wp_e_events (
                    id bigserial primary key,
                    event_data text null,
                    created_at timestamp not null
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($postgresql), trim($expected));
    }


    public function test_it_handles_keys() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_itsec_dashboard_lockouts (
                id int NOT NULL AUTO_INCREMENT,
                ip varchar(40),
                time timestamp NOT NULL,
                count int NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY ip__time (ip, time)
            )
        SQL;
        
        $expected = <<<SQL
            CREATE TABLE wp_itsec_dashboard_lockouts (
                id serial,
                ip varchar(40),
                time timestamp NOT NULL,
                count int NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (ip, time)
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($postgresql), trim($expected));
    }

    public function test_it_handles_keys_without_unique() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_itsec_vulnerabilities (
                id varchar(128) NOT NULL,
                software_type varchar(20) NOT NULL,
                software_slug varchar(255) NOT NULL,
                first_seen timestamp NOT NULL,
                last_seen timestamp NOT NULL,
                resolved_at timestamp default NULL,
                resolved_by bigint NOT NULL default 0,
                resolution varchar(20) NOT NULL default '',
                details text NOT NULL,
                PRIMARY KEY (id),
                KEY resolution (resolution),
                KEY software_type (software_type),
                KEY last_seen (last_seen)
            )
        SQL;
        
        $expected = <<<SQL
            CREATE TABLE wp_itsec_vulnerabilities (
                id varchar(128) NOT NULL,
                software_type varchar(20) NOT NULL,
                software_slug varchar(255) NOT NULL,
                first_seen timestamp NOT NULL,
                last_seen timestamp NOT NULL,
                resolved_at timestamp default NULL,
                resolved_by bigint NOT NULL default 0,
                resolution varchar(20) NOT NULL default '',
                details text NOT NULL,
                PRIMARY KEY (id)
            );
        CREATE INDEX wp_itsec_vulnerabilities_resolution ON wp_itsec_vulnerabilities (resolution);
        CREATE INDEX wp_itsec_vulnerabilities_software_type ON wp_itsec_vulnerabilities (software_type);
        CREATE INDEX wp_itsec_vulnerabilities_last_seen ON wp_itsec_vulnerabilities (last_seen);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($postgresql), trim($expected));
    }

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class () {
            public $categories = "wp_categories";
            public $comments = "wp_comments";
            public $prefix = "wp_";
            public $options = "wp_options";
        };
    }
}
