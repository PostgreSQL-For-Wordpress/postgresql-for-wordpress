<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

const ABSPATH = __DIR__ . "/../";
const WPINC = "wp-includes";

require_once __DIR__ . "/../pg4wp/db.php";

final class verifyAgainstStubsTest extends TestCase
{
    const STUBS_DIRECTORY = __DIR__ . '/stubs';

    public function test_verify_against_stubs()
    {
        $files = array_diff(scandir(self::STUBS_DIRECTORY), array('.', '..'));
        foreach($files as $file) {
            $data = json_decode(file_get_contents(self::STUBS_DIRECTORY . "/" . $file), true);
            $this->assertSame(pg4wp_rewrite($data['mysql']), $data['postgresql']);
        }

    }
}