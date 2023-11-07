<?php

class CreateTableSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        // List of types translations (the key is the mysql one, the value is the text to use instead)
        $typeTranslations = array(
            'bigint(20)'	=> 'bigint',
            'bigint(10)'	=> 'int',
            'int(11)'		=> 'int',
            'tinytext'		=> 'text',
            'mediumtext'	=> 'text',
            'longtext'		=> 'text',
            'unsigned'		=> '',
            'gmt datetime NOT NULL default \'0000-00-00 00:00:00\''	=> 'gmt timestamp NOT NULL DEFAULT timezone(\'gmt\'::text, now())',
            'default \'0000-00-00 00:00:00\''	=> 'DEFAULT now()',
            '\'0000-00-00 00:00:00\''	=> 'now()',
            'datetime'		=> 'timestamp',
            'DEFAULT CHARACTER SET utf8mb4' => '',
            'DEFAULT CHARACTER SET utf8'	=> '',

            // WP 2.7.1 compatibility
            'int(4)'		=> 'smallint',

            // For WPMU (starting with WP 3.2)
            'tinyint(2)'	=> 'smallint',
            'tinyint(1)'	=> 'smallint',
            "enum('0','1')"	=> 'smallint',
            'COLLATE utf8mb4_unicode_520_ci'	=> '',
            'COLLATE utf8_general_ci'	=> '',

            // For flash-album-gallery plugin
            'tinyint'		=> 'smallint',
        );

        $sql = $this->original();
        $sql = str_replace('CREATE TABLE IF NOT EXISTS ', 'CREATE TABLE ', $sql);
        $pattern = '/CREATE TABLE [`]?(\w+)[`]?/';
        preg_match($pattern, $sql, $matches);
        $table = $matches[1];

        // Remove trailing spaces
        $sql = trim($sql) . ';';

        // Translate types and some other replacements
        $sql = str_replace(
            array_keys($typeTranslations),
            array_values($typeTranslations),
            $sql
        );

        // Fix auto_increment by adding a sequence
        $pattern = '/int[ ]+NOT NULL auto_increment/';
        preg_match($pattern, $sql, $matches);
        if($matches) {
            $seq = $table . '_seq';
            $sql = str_replace('NOT NULL auto_increment', "NOT NULL DEFAULT nextval('$seq'::text)", $sql);
            $sql .= "\nCREATE SEQUENCE $seq;";
        }

        // Support for INDEX creation
        $pattern = '/,\s+(UNIQUE |)KEY\s+([^\s]+)\s+\(((?:[\w]+(?:\([\d]+\))?[,]?)*)\)/';
        if(preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {
                $unique = $match[1];
                $index = $match[2];
                $columns = $match[3];
                $columns = preg_replace('/\(\d+\)/', '', $columns);
                // Workaround for index name duplicate
                $index = $table . '_' . $index;
                $sql .= "\nCREATE {$unique}INDEX $index ON $table ($columns);";
            }
        }
        // Now remove handled indexes
        $sql = preg_replace($pattern, '', $sql);

        return $sql;
    }
}
