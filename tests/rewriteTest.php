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
                lockout_active int NOT NULL DEFAULT 1, 
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

    public function test_it_handles_numerics_without_auto_incrment_case_insensitively()
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS stars_votes (
                voter_ip VARCHAR(150) NOT NULL,
                post_id BIGINT(20) UNSIGNED NOT NULL,
                rating INT(1) UNSIGNED NOT NULL,
                PRIMARY KEY  (voter_ip, post_id)
            )
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS stars_votes (
                voter_ip VARCHAR(150) NOT NULL,
                post_id BIGINT  NOT NULL,
                rating INT  NOT NULL,
                PRIMARY KEY  (voter_ip, post_id)
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
                "time" timestamp NOT NULL,
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
                "time" timestamp NOT NULL,
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
                "timestamp" int NOT NULL,
                "date" timestamp NOT NULL,
                referred text NOT NULL,
                agent varchar(255) NOT NULL,
                platform varchar(255),
                version varchar(255),
                location varchar(10),
                user_id BIGINT NOT NULL,
                page_id BIGINT NOT NULL,
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
                "date" date NOT NULL,
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

    public function test_it_will_handle_found_rows_on_queries_with_order_by_case()
    {
        $GLOBALS['pg4wp_numrows_query'] = <<<SQL
            SELECT wp_posts.ID
            FROM wp_posts 
            WHERE 1=1 AND 
            (((wp_posts.post_title LIKE '%Hello%') OR (wp_posts.post_excerpt LIKE '%Hello%') OR (wp_posts.post_content LIKE '%Hello%')) AND 
            ((wp_posts.post_title LIKE '%world%') OR (wp_posts.post_excerpt LIKE '%world%') OR (wp_posts.post_content LIKE '%world%'))) AND 
            ((wp_posts.post_type = 'post' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private'))) 
            ORDER BY (CASE
                WHEN wp_posts.post_title LIKE '%Hello world%' THEN 1 
                WHEN wp_posts.post_title LIKE '%Hello%' AND wp_posts.post_title LIKE '%world%' THEN 2 
                WHEN wp_posts.post_title LIKE '%Hello%' OR wp_posts.post_title LIKE '%world%' THEN 3 
                WHEN wp_posts.post_excerpt LIKE '%Hello world%' THEN 4 
                WHEN wp_posts.post_content LIKE '%Hello world%' THEN 5 ELSE 6 END), wp_posts.post_date 
                DESC 
            LIMIT 0, 20
        SQL;

        $sql = "SELECT FOUND_ROWS()";

        $expected = <<<SQL
            SELECT COUNT(*) FROM wp_posts 
            WHERE 1=1 AND 
            (((wp_posts.post_title ILIKE '%Hello%') OR (wp_posts.post_excerpt ILIKE '%Hello%') OR (wp_posts.post_content ILIKE '%Hello%')) AND 
            ((wp_posts.post_title ILIKE '%world%') OR (wp_posts.post_excerpt ILIKE '%world%') OR (wp_posts.post_content ILIKE '%world%'))) AND 
            ((wp_posts.post_type = 'post' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private')))
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_will_append_returning_id_to_insert_statements()
    {
        $sql = <<<SQL
            INSERT INTO wp_translations_term_relations (
                object_id, 
                object_lang, 
                source_id) 
            VALUES (%d, %s, %d) 
            ON DUPLICATE KEY 
            UPDATE object_id=VALUES(object_id), object_lang=VALUES(object_lang), source_id=VALUES(source_id);
        SQL;

        $expected = <<<SQL
            INSERT INTO wp_translations_term_relations (
                object_id, 
                object_lang, 
                source_id) 
            VALUES (%d, %s, %d) 
            ON DUPLICATE KEY 
            UPDATE object_id=VALUES(object_id), object_lang=VALUES(object_lang), source_id=VALUES(source_id) RETURNING *;
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_can_handle_replacement_sql()
    {
        $sql = "REPLACE INTO test2 (column1, column2, column3) VALUES (1, 'Old', '2014-08-20 18:47:00')";
        $expected = "INSERT INTO test2 (column1, column2, column3) VALUES (1, 'Old', '2014-08-20 18:47:00') ON CONFLICT (column1) DO UPDATE SET column2 = EXCLUDED.column2, column3 = EXCLUDED.column3 RETURNING *";

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

    public function test_it_handles_alter_tables_with_indexes() 
    {
        $sql = <<<SQL
            ALTER TABLE wp_e_events ADD INDEX `created_at_index` (`created_at`)
        SQL;

        $expected = <<<SQL
            CREATE INDEX IF NOT EXISTS wp_e_events_created_at_index ON wp_e_events (created_at)
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));

    }

    public function test_it_handles_alter_tables_with_unique_indexes() 
    {
        $sql = <<<SQL
            ALTER TABLE wp_e_events ADD UNIQUE INDEX `created_at_index` (`created_at`)
        SQL;

        $expected = <<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS wp_e_events_created_at_index ON wp_e_events (created_at)
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_protected_column_names()
    {
        $sql = <<<SQL
            CREATE TABLE wp_cmplz_cookiebanners (
                "ID" int NOT NULL DEFAULT nextval('wp_cmplz_cookiebanners_seq'::text),
                banner_version int NOT NULL,
                default int NOT NULL
            );
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_cmplz_cookiebanners (
                 "ID"  int NOT NULL DEFAULT nextval('wp_cmplz_cookiebanners_seq'::text),
                banner_version int NOT NULL,
                "default" int NOT NULL
            );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_advanced_protected_column_names()
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
                "date" date NOT NULL,
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
    

    public function test_it_doesnt_remove_single_quotes() 
    {
        $sql = <<<SQL
            SELECT COUNT(*) FROM wp_comments WHERE user_id = 5 AND comment_approved = '1'
        SQL;

        $expected = <<<SQL
            SELECT COUNT(*) FROM wp_comments WHERE user_id = 5 AND comment_approved = '1'
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_can_handle_insert_sql_containing_nested_parathesis_with_numbers()
    {
        $sql = <<<SQL
            REPLACE INTO `wp_options` (`option_name`, `option_value`, `autoload`) VALUES ('_site_transient_wp_remote_block_patterns_b815a6cec4e03bb064328ac11645ce66', 'a:43:{i:0;O:8:"stdClass":7:{s:2:"id";i:309935;s:5:"title";O:8:"stdClass":1:{s:8:"rendered";s:45:"Centered image with two-tone background color";}s:7:"content";O:8:"stdClass":2:{s:8:"rendered";s

            <div class="wp-block-cover alignfull is-light" style="margin-top:0;padding-top:5vw;padding-right:5vw;padding-bottom:5vw;padding-left:5vw;min-height:66vh;aspect-ratio:unset;aspect-ratio:unset;"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-grad
            <div class="wp-block-group wp-container-content-2 is-layout-constrained wp-container-core-group-is-layout-1 wp-block-group-is-layout-constrained">
            !-- /wp:image -->

            <!-- wp:paragraph {"align":"right","style":{"typography":{"fontSize":"148px","textTransform":"uppercase","fontStyle":"normal","fontWeight":"700","lineHeight":"0.8","letterSpacing":"-4px"}},"textColor":"white"} -->
            <p class="has-text-align-right has-white-color has-text-color" style="font-size:148px;font-style:normal;font-weight:700;letter-spacing:-4px;line-height:0.8;text-transform:uppercase">Big<br>John<br>Patton</p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:group --></div>
            <!-- /wp:group --></div></div>
            <!-- /wp:cover -->";}i:4;O:8:"stdClass":7:{s:2:"id";i:309236;s:5:"title";O:8:"stdClass":1:{s:8:"rendered";s:60:"Fullwidth headline with links and gradient offset background";}s:7:"content";O:8:"stdClass":2:{s:8:"rendered";s:1972:"
            <div class="wp-block-cover alignfull is-light" style="margin-top:0;padding-top:48px;padding-right:5vw;padding-bottom:48px;padding-left:5vw"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-gradient(180deg,rgb(0,0,0) 39%,rgb(83,80,123) 39%)"></spa
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>



            <!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="https://s.w.org/images/core/5.8/nature-above-02.jpg" alt="An aerial view of a field. A road runs through the upper right corner." /></figure>
            <!-- /wp:image --></figure>
            <!-- /wp:gallery -->";}}', 'no')
        SQL;
        $expected = <<<SQL
            INSERT INTO "wp_options" ("option_name", "option_value", "autoload") VALUES ('_site_transient_wp_remote_block_patterns_b815a6cec4e03bb064328ac11645ce66', 'a:43:{i:0;O:8:"stdClass":7:{s:2:"id";i:309935;s:5:"title";O:8:"stdClass":1:{s:8:"rendered";s:45:"Centered image with two-tone background color";}s:7:"content";O:8:"stdClass":2:{s:8:"rendered";s

            <div class="wp-block-cover alignfull is-light" style="margin-top:0;padding-top:5vw;padding-right:5vw;padding-bottom:5vw;padding-left:5vw;min-height:66vh;aspect-ratio:unset;aspect-ratio:unset;"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-grad
            <div class="wp-block-group wp-container-content-2 is-layout-constrained wp-container-core-group-is-layout-1 wp-block-group-is-layout-constrained">
            !-- /wp:image -->

            <!-- wp:paragraph {"align":"right","style":{"typography":{"fontSize":"148px","textTransform":"uppercase","fontStyle":"normal","fontWeight":"700","lineHeight":"0.8","letterSpacing":"-4px"}},"textColor":"white"} -->
            <p class="has-text-align-right has-white-color has-text-color" style="font-size:148px;font-style:normal;font-weight:700;letter-spacing:-4px;line-height:0.8;text-transform:uppercase">Big<br>John<br>Patton</p>
            <!-- /wp:paragraph --></div>
            <!-- /wp:group --></div>
            <!-- /wp:group --></div></div>
            <!-- /wp:cover -->";}i:4;O:8:"stdClass":7:{s:2:"id";i:309236;s:5:"title";O:8:"stdClass":1:{s:8:"rendered";s:60:"Fullwidth headline with links and gradient offset background";}s:7:"content";O:8:"stdClass":2:{s:8:"rendered";s:1972:"
            <div class="wp-block-cover alignfull is-light" style="margin-top:0;padding-top:48px;padding-right:5vw;padding-bottom:48px;padding-left:5vw"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-gradient(180deg,rgb(0,0,0) 39%,rgb(83,80,123) 39%)"></spa
            <div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>



            <!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="https://s.w.org/images/core/5.8/nature-above-02.jpg" alt="An aerial view of a field. A road runs through the upper right corner." /></figure>
            <!-- /wp:image --></figure>
            <!-- /wp:gallery -->";}}', 'no') ON CONFLICT ("option_name") DO UPDATE SET "option_value" = EXCLUDED."option_value", "autoload" = EXCLUDED."autoload" RETURNING * 
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_mediumints() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_relevanssi (
                doc bigint(20) NOT NULL DEFAULT '0',
                term varchar(50) NOT NULL DEFAULT '0',
                term_reverse varchar(50) NOT NULL DEFAULT '0',
                content mediumint(9) NOT NULL DEFAULT '0',
                title mediumint(9) NOT NULL DEFAULT '0',
                comment mediumint(9) NOT NULL DEFAULT '0',
                tag mediumint(9) NOT NULL DEFAULT '0',
                link mediumint(9) NOT NULL DEFAULT '0',
                author mediumint(9) NOT NULL DEFAULT '0',
                category mediumint(9) NOT NULL DEFAULT '0',
                excerpt mediumint(9) NOT NULL DEFAULT '0',
                taxonomy mediumint(9) NOT NULL DEFAULT '0',
                customfield mediumint(9) NOT NULL DEFAULT '0',
                mysqlcolumn MEDIUMINT(9) NOT NULL DEFAULT '0',
                taxonomy_detail longtext NOT NULL,
                customfield_detail longtext NOT NULL DEFAULT '',
                mysqlcolumn_detail longtext NOT NULL DEFAULT '',
                type varchar(210) NOT NULL DEFAULT 'post',
                item bigint(20) NOT NULL DEFAULT '0',
                PRIMARY KEY doctermitem (doc, term, item)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_relevanssi (
                doc bigint NOT NULL DEFAULT '0',
                term varchar(50) NOT NULL DEFAULT '0',
                term_reverse varchar(50) NOT NULL DEFAULT '0',
                content integer NOT NULL DEFAULT '0',
                title integer NOT NULL DEFAULT '0',
                comment integer NOT NULL DEFAULT '0',
                tag integer NOT NULL DEFAULT '0',
                link integer NOT NULL DEFAULT '0',
                author integer NOT NULL DEFAULT '0',
                category integer NOT NULL DEFAULT '0',
                excerpt integer NOT NULL DEFAULT '0',
                taxonomy integer NOT NULL DEFAULT '0',
                customfield integer NOT NULL DEFAULT '0',
                mysqlcolumn integer NOT NULL DEFAULT '0',
                taxonomy_detail text NOT NULL,
                customfield_detail text NOT NULL DEFAULT '',
                mysqlcolumn_detail text NOT NULL DEFAULT '',
                type varchar(210) NOT NULL DEFAULT 'post',
                item bigint NOT NULL DEFAULT '0',
                PRIMARY KEY (doc, term, item)
                );
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_0CASTS() 
    {

        $sql = <<<SQL
            SELECT   wp_posts.* FROM wp_posts  INNER JOIN wp_postmeta ON ( wp_posts.ID = wp_postmeta.post_id )
            WHERE 1=1  AND ( 
                wp_posts.post_date > '2024-07-17 23:59:59'
            ) AND ( 
                wp_postmeta.meta_key = 'make_feature_post'
            ) AND (
                (wp_posts.post_type = 'announcement' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'acf-disabled' OR wp_posts.post_status = 'private'))
            )
            GROUP BY wp_posts.ID
            ORDER BY wp_postmeta.meta_value+0 DESC, wp_posts.post_date DESC
        SQL;

        $expected = <<<SQL
            SELECT   wp_posts.* FROM wp_posts  INNER JOIN wp_postmeta ON ( wp_posts."ID" = wp_postmeta.post_id )
            WHERE 1=1  AND ( 
                wp_posts.post_date > '2024-07-17 23:59:59'
            ) AND ( 
                wp_postmeta.meta_key = 'make_feature_post'
            ) AND (
                (wp_posts.post_type = 'announcement' AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'acf-disabled' OR wp_posts.post_status = 'private'))
            )
            
            ORDER BY CAST(wp_postmeta.meta_value AS INTEGER) DESC, wp_posts.post_date DESC
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_regexp() 
    {
        $sql = <<<SQL
            SELECT DISTINCT meta_key FROM wp_gf_entry_meta WHERE form_id=2 AND meta_key REGEXP '^[0-9]+(\.[0-9]+)?$'
        SQL;

        $expected = <<<SQL
            SELECT DISTINCT meta_key FROM wp_gf_entry_meta WHERE form_id=2 AND meta_key ~ '^[0-9]+(\.[0-9]+)?$'
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_utc_timestamp_inserts() 
    {
        $sql = <<<SQL
            INSERT INTO wp_gf_form(title, date_created) VALUES('Test', utc_timestamp())
        SQL;

        $expected = <<<SQL
            INSERT INTO wp_gf_form(title, date_created) VALUES('Test', CURRENT_TIMESTAMP AT TIME ZONE 'UTC') RETURNING *
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_rewrites_utc_timestamp_selects() 
    {
        $sql = <<<SQL
            SELECT utc_timestamp()
        SQL;

        $expected = <<<SQL
            SELECT CURRENT_TIMESTAMP AT TIME ZONE 'UTC'
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($expected), trim($postgresql));
    }

    public function test_it_quotes_reserved_columns() 
    {
        $sql = <<<SQL
            CREATE TABLE wp_aioseo_notifications (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                slug varchar(13) NOT NULL,
                title text NOT NULL,
                content longtext NOT NULL,
                type varchar(64) NOT NULL,
                level text NOT NULL,
                notification_id bigint(20) unsigned DEFAULT NULL,
                notification_name varchar(255) DEFAULT NULL,
                start datetime DEFAULT NULL,
                end datetime DEFAULT NULL,
                button1_label varchar(255) DEFAULT NULL,
                button1_action varchar(255) DEFAULT NULL,
                button2_label varchar(255) DEFAULT NULL,
                button2_action varchar(255) DEFAULT NULL,
                dismissed tinyint(1) NOT NULL DEFAULT 0,
                created datetime NOT NULL,
                updated datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY ndx_aioseo_notifications_slug (slug),
                KEY ndx_aioseo_notifications_dates (start, end),
                KEY ndx_aioseo_notifications_type (type),
                KEY ndx_aioseo_notifications_dismissed (dismissed)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
        SQL;

        $expected = <<<SQL
            CREATE TABLE IF NOT EXISTS wp_aioseo_notifications (
                id bigserial,
                slug varchar(13) NOT NULL,
                title text NOT NULL,
                content text NOT NULL,
                type varchar(64) NOT NULL,
                level text NOT NULL,
                notification_id bigint  DEFAULT NULL,
                notification_name varchar(255) DEFAULT NULL,
                start timestamp DEFAULT NULL,
                "end" timestamp DEFAULT NULL,
                button1_label varchar(255) DEFAULT NULL,
                button1_action varchar(255) DEFAULT NULL,
                button2_label varchar(255) DEFAULT NULL,
                button2_action varchar(255) DEFAULT NULL,
                dismissed smallint NOT NULL DEFAULT 0,
                created timestamp NOT NULL,
                updated timestamp NOT NULL,
                PRIMARY KEY (id)
            );
        CREATE UNIQUE INDEX IF NOT EXISTS wp_aioseo_notifications_ndx_aioseo_notifications_slug ON wp_aioseo_notifications (slug);
        CREATE INDEX IF NOT EXISTS wp_aioseo_notifications_ndx_aioseo_notifications_dates ON wp_aioseo_notifications (start, end);
        CREATE INDEX IF NOT EXISTS wp_aioseo_notifications_ndx_aioseo_notifications_type ON wp_aioseo_notifications (type);
        CREATE INDEX IF NOT EXISTS wp_aioseo_notifications_ndx_aioseo_notifications_dismissed ON wp_aioseo_notifications (dismissed);
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
            public $sitemeta = "wp_sitemeta";
        };
    }
    
    public function test_it_properly_uses_dynamic_table_prefix_for_delete_queries()
    {
        global $wpdb;
        
        // Change the prefix to a custom one
        $wpdb->prefix = "custom_";
        $wpdb->options = "custom_options";
        $wpdb->sitemeta = "custom_sitemeta";
        $wpdb->posts = "custom_posts";
        $wpdb->postmeta = "custom_postmeta";
        
        // Test DELETE with options table
        $sql = "DELETE a, b FROM custom_options a, custom_options b WHERE a.option_name = '_transient_timeout_something' AND b.option_name = '_transient_something' AND b.option_value < 12345678";
        $postgresql = pg4wp_rewrite($sql);
        $this->assertStringContainsString("DELETE FROM custom_options a USING custom_options b", $postgresql);
        $this->assertStringNotContainsString("wp_options", $postgresql);
        
        // Test DELETE with sitemeta table
        $sql = "DELETE a, b FROM custom_sitemeta a, custom_sitemeta b WHERE a.meta_key = '_site_transient_timeout_something' AND b.meta_key = '_site_transient_something' AND b.meta_value < 12345678";
        $postgresql = pg4wp_rewrite($sql);
        $this->assertStringContainsString("DELETE FROM custom_sitemeta a USING custom_sitemeta b", $postgresql);
        $this->assertStringNotContainsString("wp_sitemeta", $postgresql);
        
        // Test general pattern DELETE with any tables
        $sql = "DELETE p, pm FROM custom_posts p, custom_postmeta pm WHERE p.ID = pm.post_id AND p.post_type = 'revision'";
        $postgresql = pg4wp_rewrite($sql);
        $this->assertStringContainsString("DELETE FROM custom_posts p USING custom_postmeta pm", $postgresql);
        $this->assertStringNotContainsString("wp_posts", $postgresql);
        $this->assertStringNotContainsString("wp_postmeta", $postgresql);
        
        // Test with tables that don't exist as $wpdb properties
        $sql = "DELETE a, b FROM custom_mytable a, custom_anothertable b WHERE a.id = b.ref_id";
        $postgresql = pg4wp_rewrite($sql);
        $this->assertStringContainsString("DELETE FROM custom_mytable a USING custom_anothertable b", $postgresql);
        
        // Restore the original prefix for other tests
        $wpdb->prefix = "wp_";
        $wpdb->options = "wp_options";
        $wpdb->sitemeta = "wp_sitemeta";
        $wpdb->posts = "wp_posts";
        $wpdb->postmeta = "wp_postmeta";
    }
}
