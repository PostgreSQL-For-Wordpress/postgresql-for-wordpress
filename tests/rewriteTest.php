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
        $this->assertSame(trim($expected), trim($postgresql));
    }


    public function test_it_adds_group_by()
    {

        $sql = 'SELECT COUNT(id), username FROM users';
        $expected = 'SELECT COUNT(id) AS count0, username FROM users GROUP BY username';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
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
            CREATE TABLE IF NOT EXISTS wp_itsec_lockouts (
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
        $this->assertSame(trim($expected), trim($postgresql));
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
            CREATE TABLE IF NOT EXISTS wp_e_events (
                    id bigserial primary key,
                    event_data text null,
                    created_at timestamp not null
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
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
            CREATE TABLE IF NOT EXISTS wp_itsec_dashboard_lockouts (
                id serial,
                ip varchar(40),
                time timestamp NOT NULL,
                count int NOT NULL,
                PRIMARY KEY (id)
            );
        CREATE UNIQUE INDEX IF NOT EXISTS wp_itsec_dashboard_lockouts_ip__time ON wp_itsec_dashboard_lockouts (ip, time);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
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
            CREATE TABLE IF NOT EXISTS wp_itsec_vulnerabilities (
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
        CREATE INDEX IF NOT EXISTS wp_itsec_vulnerabilities_resolution ON wp_itsec_vulnerabilities (resolution);
        CREATE INDEX IF NOT EXISTS wp_itsec_vulnerabilities_software_type ON wp_itsec_vulnerabilities (software_type);
        CREATE INDEX IF NOT EXISTS wp_itsec_vulnerabilities_last_seen ON wp_itsec_vulnerabilities (last_seen);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_does_not_remove_if_not_exists()
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_itsec_dashboard_lockouts (
                id int NOT NULL AUTO_INCREMENT,
                ip varchar(40),
                time timestamp NOT NULL,
                count int NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY ip__time (ip, time)
            )
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_itsec_dashboard_lockouts (
                id serial,
                ip varchar(40),
                time timestamp NOT NULL,
                count int NOT NULL,
                PRIMARY KEY (id)
            );
        CREATE UNIQUE INDEX IF NOT EXISTS wp_itsec_dashboard_lockouts_ip__time ON wp_itsec_dashboard_lockouts (ip, time);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }


    public function test_it_removes_character_sets()
    {
        $sql = <<<SQL
            CREATE TABLE wp_statistics_useronline (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                ip varchar(60) NOT NULL,
                created int(11),
                timestamp int(10) NOT NULL,
                date datetime NOT NULL,
                referred text CHARACTER SET utf8 NOT NULL,
                agent varchar(255) NOT NULL,
                platform varchar(255),
                version varchar(255),
                location varchar(10),
                `user_id` BIGINT(48) NOT NULL,
                `page_id` BIGINT(48) NOT NULL,
                `type` VARCHAR(100) NOT NULL,
                PRIMARY KEY  (ID)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_statistics_useronline (
                "ID" bigserial,
                ip varchar(60) NOT NULL,
                created int,
                timestamp int NOT NULL,
                date timestamp NOT NULL,
                referred text NOT NULL,
                agent varchar(255) NOT NULL,
                platform varchar(255),
                version varchar(255),
                location varchar(10),
                user_id BIGINT(48) NOT NULL,
                page_id BIGINT(48) NOT NULL,
                type VARCHAR(100) NOT NULL,
                PRIMARY KEY  ( "ID" )
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_handles_multiple_keys()
    {
        $sql = <<<SQL
            CREATE TABLE wp_statistics_pages (
                page_id BIGINT(20) NOT NULL AUTO_INCREMENT,
                uri varchar(190) NOT NULL,
                type varchar(180) NOT NULL,
                date date NOT NULL,
                count int(11) NOT NULL,
                id int(11) NOT NULL,
                UNIQUE KEY date_2 (date,uri),
                KEY url (uri),
                KEY date (date),
                KEY id (id),
                KEY `uri` (`uri`,`count`,`id`),
                PRIMARY KEY (`page_id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_statistics_pages (
                page_id bigserial,
                uri varchar(190) NOT NULL,
                type varchar(180) NOT NULL,
                date date NOT NULL,
                count int NOT NULL,
                id int NOT NULL,
                PRIMARY KEY (page_id)
            );
        CREATE UNIQUE INDEX IF NOT EXISTS wp_statistics_pages_date_2 ON wp_statistics_pages (date,uri);
        CREATE INDEX IF NOT EXISTS wp_statistics_pages_url ON wp_statistics_pages (uri);
        CREATE INDEX IF NOT EXISTS wp_statistics_pages_date ON wp_statistics_pages (date);
        CREATE INDEX IF NOT EXISTS wp_statistics_pages_id ON wp_statistics_pages (id);
        CREATE INDEX IF NOT EXISTS wp_statistics_pages_uri ON wp_statistics_pages (uri,count,id);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }


    public function test_it_removes_table_charsets()
    {
        $sql = <<<SQL
            CREATE TABLE `wp_yoast_migrations` (
                `id` int(11) UNSIGNED auto_increment NOT NULL,
                `version` varchar(191),
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8;
        SQL;

        $expected = <<<SQL
            CREATE TABLE wp_yoast_migrations (
                id serial NOT NULL,
                version varchar(191),
                PRIMARY KEY (id)
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }


    public function test_it_can_create_keys_with_length()
    {
        $sql = <<<SQL
            CREATE TABLE wp_usermeta (
                umeta_id bigint(20) unsigned NOT NULL auto_increment,
                user_id bigint(20) unsigned NOT NULL default '0',
                meta_key varchar(255) default NULL,
                meta_value longtext,
                PRIMARY KEY (umeta_id),
                KEY user_id (user_id),
                KEY meta_key (meta_key(191))
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_usermeta (
                umeta_id bigserial,
                user_id bigint  NOT NULL default '0',
                meta_key varchar(255) default NULL,
                meta_value text,
                PRIMARY KEY (umeta_id)
            );
        CREATE INDEX IF NOT EXISTS wp_usermeta_user_id ON wp_usermeta (user_id);
        CREATE INDEX IF NOT EXISTS wp_usermeta_meta_key ON wp_usermeta (meta_key);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }



    public function test_it_can_create_double_keys_with_length()
    {
        $sql = <<<SQL
            CREATE TABLE wp_blogs (
                blog_id bigint(20) NOT NULL auto_increment,
                site_id bigint(20) NOT NULL default '0',
                domain varchar(200) NOT NULL default '',
                path varchar(100) NOT NULL default '',
                registered datetime NOT NULL default '0000-00-00 00:00:00',
                last_updated datetime NOT NULL default '0000-00-00 00:00:00',
                public tinyint(2) NOT NULL default '1',
                archived tinyint(2) NOT NULL default '0',
                mature tinyint(2) NOT NULL default '0',
                spam tinyint(2) NOT NULL default '0',
                deleted tinyint(2) NOT NULL default '0',
                lang_id int(11) NOT NULL default '0',
                PRIMARY KEY  (blog_id),
                KEY domain (domain(50),path(5)),
                KEY lang_id (lang_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_blogs (
                blog_id bigserial,
                site_id bigint NOT NULL default '0',
                domain varchar(200) NOT NULL default '',
                path varchar(100) NOT NULL default '',
                registered timestamp NOT NULL DEFAULT now(),
                last_updated timestamp NOT NULL DEFAULT now(),
                public smallint NOT NULL default '1',
                archived smallint NOT NULL default '0',
                mature smallint NOT NULL default '0',
                spam smallint NOT NULL default '0',
                deleted smallint NOT NULL default '0',
                lang_id int NOT NULL default '0',
                PRIMARY KEY  (blog_id)
            );
        CREATE INDEX IF NOT EXISTS wp_blogs_domain ON wp_blogs (domain,path);
        CREATE INDEX IF NOT EXISTS wp_blogs_lang_id ON wp_blogs (lang_id);
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_doesnt_rewrite_when_it_doesnt_need_to()
    {
        $sql = <<<SQL
            SELECT p.ID FROM wp_posts p 
            WHERE post_type='scheduled-action' 
            AND p.post_status IN ('pending') 
            AND p.post_modified_gmt <= '2023-11-27 14:23:34' 
            AND p.post_password != '' ORDER BY p.post_date_gmt ASC LIMIT 0, 20
        SQL;

        $expected = <<<SQL
            SELECT p."ID" , p.post_date_gmt FROM wp_posts p 
            WHERE post_type='scheduled-action' 
            AND p.post_status IN ('pending') 
            AND p.post_modified_gmt <= '2023-11-27 14:23:34' 
            AND p.post_password != '' ORDER BY p.post_date_gmt ASC LIMIT 20 OFFSET 0
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
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
