<?php

class UpdateSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

        $pattern = '/LIMIT[ ]+\d+/';
        $sql = preg_replace($pattern, '', $sql);

        // Fix update wp_options
        $pattern = "/UPDATE `wp_options` SET `option_value` = NULL WHERE `option_name` = '(.+)'/";
        $match = "UPDATE `wp_options` SET `option_value` = '' WHERE `option_name` = '$1'";
        $sql = preg_replace($pattern, $match, $sql);

        // For correct bactick removal
        $pattern = '/[ ]*`([^` ]+)`[ ]*=/';
        $sql = preg_replace($pattern, ' $1 =', $sql);

        // Those are used when we need to set the date to now() in gmt time
        $sql = str_replace("'0000-00-00 00:00:00'", 'now() AT TIME ZONE \'gmt\'', $sql);

        // For correct ID quoting
        $pattern = '/(,|\s)[ ]*([^ \']*ID[^ \']*)[ ]*=/';
        $sql = preg_replace($pattern, '$1 "$2" =', $sql);

        return $sql;
    }
}
