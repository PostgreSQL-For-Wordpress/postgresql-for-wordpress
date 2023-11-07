<?php

class AlterTableSQLRewriter extends AbstractSQLRewriter
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
            'DEFAULT CHARACTER SET utf8'	=> '',

            // WP 2.7.1 compatibility
            'int(4)'		=> 'smallint',

            // For WPMU (starting with WP 3.2)
            'tinyint(2)'	=> 'smallint',
            'tinyint(1)'	=> 'smallint',
            "enum('0','1')"	=> 'smallint',
            'COLLATE utf8_general_ci'	=> '',

            // For flash-album-gallery plugin
            'tinyint'		=> 'smallint',
        );
        $pattern = '/ALTER TABLE\s+(\w+)\s+CHANGE COLUMN\s+([^\s]+)\s+([^\s]+)\s+([^ ]+)( unsigned|)\s*(NOT NULL|)\s*(default (.+)|)/';
        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $newname = $matches[3];
            $type = strtolower($matches[4]);
            if(isset($typeTranslations[$type])) {
                $type = $typeTranslations[$type];
            }
            $unsigned = $matches[5];
            $notnull = $matches[6];
            $default = $matches[7];
            $defval = $matches[8];
            if(isset($typeTranslations[$defval])) {
                $defval = $typeTranslations[$defval];
            }
            $newq = "ALTER TABLE $table ALTER COLUMN $col TYPE $type";
            if(!empty($notnull)) {
                $newq .= ", ALTER COLUMN $col SET NOT NULL";
            }
            if(!empty($default)) {
                $newq .= ", ALTER COLUMN $col SET DEFAULT $defval";
            }
            if($col != $newname) {
                $newq .= ";ALTER TABLE $table RENAME COLUMN $col TO $newcol;";
            }
            $sql = $newq;
        }
        $pattern = '/ALTER TABLE\s+(\w+)\s+ALTER COLUMN\s+/';
        if(1 === preg_match($pattern, $sql)) {
            // Translate default values
            $sql = str_replace(
                array_keys($typeTranslations),
                array_values($typeTranslations),
                $sql
            );
        }
        $pattern = '/ALTER TABLE\s+(\w+)\s+ADD COLUMN\s+([^\s]+)\s+([^ ]+)( unsigned|)\s+(NOT NULL|)\s*(default (.+)|)/';
        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $type = strtolower($matches[3]);
            if(isset($typeTranslations[$type])) {
                $type = $typeTranslations[$type];
            }
            $unsigned = $matches[4];
            $notnull = $matches[5];
            $default = $matches[6];
            $defval = $matches[7];
            if(isset($typeTranslations[$defval])) {
                $defval = $typeTranslations[$defval];
            }
            $newq = "ALTER TABLE $table ADD COLUMN $col $type";
            if(!empty($default)) {
                $newq .= " DEFAULT $defval";
            }
            if(!empty($notnull)) {
                $newq .= " NOT NULL";
            }
            $sql = $newq;
        }
        $pattern = '/ALTER TABLE\s+(\w+)\s+ADD (UNIQUE |)KEY\s+([^\s]+)\s+\(((?:[^\(\)]+|\([^\(\)]+\))+)\)/';
        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $unique = $matches[2];
            $index = $matches[3];
            $columns = $matches[4];

            // Remove prefix indexing
            // Rarely used and apparently unnecessary for current uses
            $columns = preg_replace('/\([^\)]*\)/', '', $columns);

            // Workaround for index name duplicate
            $index = $table . '_' . $index;
            $sql = "CREATE {$unique}INDEX $index ON $table ($columns)";
        }
        $pattern = '/ALTER TABLE\s+(\w+)\s+DROP INDEX\s+([^\s]+)/';
        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $index = $matches[2];
            $sql = "DROP INDEX ${table}_${index}";
        }
        $pattern = '/ALTER TABLE\s+(\w+)\s+DROP PRIMARY KEY/';
        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $sql = "ALTER TABLE ${table} DROP CONSTRAINT ${table}_pkey";
        }

        return $sql;
    }
}
