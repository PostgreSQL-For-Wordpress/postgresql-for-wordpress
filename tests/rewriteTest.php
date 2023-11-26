<?php declare(strict_types=1);
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
                lockout_id bigint  NOT NULL DEFAULT nextval('wp_itsec_lockouts_seq'::text), 
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
        CREATE SEQUENCE wp_itsec_lockouts_seq;
        SQL;

        $postgresql = pg4wp_rewrite($sql);
        $this->assertSame(trim($postgresql), trim($expected));
    }
}