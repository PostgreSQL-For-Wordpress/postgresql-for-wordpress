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

final class verifyAgainstStubsTest extends TestCase
{
    public const STUBS_DIRECTORY = __DIR__ . '/stubs';

    public function test_verify_against_stubs()
    {
        $files = array_diff(scandir(self::STUBS_DIRECTORY), array('.', '..'));
        foreach($files as $file) {
            $data = json_decode(file_get_contents(self::STUBS_DIRECTORY . "/" . $file), true);
            $this->assertSame($data['postgresql'], pg4wp_rewrite($data['mysql']));
        }
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
