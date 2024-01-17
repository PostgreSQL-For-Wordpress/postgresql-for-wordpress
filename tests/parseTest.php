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

final class parseTest extends TestCase
{
    public function test_it_can_parse_a_theme_change_correctly()
    {
        $sql = 'INSERT INTO "wp_options" ("option_name", "option_value", "autoload") VALUES (\'theme_switch_menu_locations\', \'a:0:{}\', \'yes\') ON CONFLICT ("option_name") DO UPDATE SET "option_name" = EXCLUDED."option_name", "option_value" = EXCLUDED."option_value", "autoload" = EXCLUDED."autoload"';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame($GLOBALS['pg4wp_ins_table'], "wp_options");
        $this->assertSame($GLOBALS['pg4wp_ins_field'], "option_name");
    }


    public function test_it_can_parse_a_page_creation_correctly()
    {

        $sql = 'INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_content_filtered, post_title, post_excerpt, post_status, post_type, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_mime_type, guid) VALUES (\'1\', \'2023-10-31 03:54:02\', now() AT TIME ZONE \'gmt\', \'\', \'\', \'Auto Draft\', \'\', \'auto-draft\', \'page\', \'closed\', \'closed\', \'\', \'\', \'\', \'\', \'2023-10-31 03:54:02\', now() AT TIME ZONE \'gmt\', 0, 0, \'\', \'\')';
        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame($GLOBALS['pg4wp_ins_table'], "wp_posts");
        $this->assertSame($GLOBALS['pg4wp_ins_field'], "post_author");
    }

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class () {
            public $options = "wp_options";
            public $categories = "wp_categories";
        };
    }

}
