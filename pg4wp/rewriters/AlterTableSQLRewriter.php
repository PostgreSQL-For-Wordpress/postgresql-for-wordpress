<?php

class AlterTableSQLRewriter extends AbstractSQLRewriter
{
    private $stringReplacements = [
        ' tinytext'		=> ' text',
        ' mediumtext'	=> ' text',
        ' longtext'		=> ' text',
        ' unsigned'		=> ' ',
        'gmt datetime NOT NULL default \'0000-00-00 00:00:00\''	=> 'gmt timestamp NOT NULL DEFAULT timezone(\'gmt\'::text, now())',
        'default \'0000-00-00 00:00:00\''	=> 'DEFAULT now()',
        '\'0000-00-00 00:00:00\''	=> 'now()',
        ' datetime'		=> ' timestamp',
        ' DEFAULT CHARACTER SET utf8mb4' => '',
        ' DEFAULT CHARACTER SET utf8'	=> '',

        // For WPMU (starting with WP 3.2)
        " enum('0','1')"	=> ' smallint',
        ' COLLATE utf8mb4_unicode_520_ci'	=> '',
        ' COLLATE utf8_general_ci'	=> '',
        ' CHARACTER SET utf8' => '',
        ' DEFAULT CHARSET=utf8' => '',

        // For flash-album-gallery plugin
        ' tinyint'		=> ' smallint'
    ];

    public function rewrite(): string
    {
        $sql = $this->original();
        
        $sql = $this->rewrite_numeric_type($sql);
        $sql = $this->rewrite_columns_with_protected_names($sql);

        if (str_contains($sql, 'ADD INDEX') || str_contains($sql, 'ADD UNIQUE INDEX')) {
            $sql = $this->rewriteAddIndex($sql);
            return $sql;
        }
        if (str_contains($sql, 'CHANGE COLUMN')) {
            $sql = $this->rewriteChangeColumn($sql);
            return $sql;
        }
        if (str_contains($sql, 'ALTER COLUMN')) {
            $sql = $this->rewriteAlterColumn($sql);
            return $sql;
        }
        if (str_contains($sql, 'ADD COLUMN')) {
            $sql = $this->rewriteAddColumn($sql);
            return $sql;
        }
        if (str_contains($sql, 'ADD KEY') || str_contains($sql, 'ADD UNIQUE KEY')) {
            $sql = $this->rewriteAddKey($sql);
            return $sql;
        }
        if (str_contains($sql, 'DROP INDEX')) {
            $sql = $this->rewriteDropIndex($sql);
            return $sql;
        }
        if (str_contains($sql, 'DROP PRIMARY KEY')) {
            $sql = $this->rewriteDropPrimaryKey($sql);
            return $sql;
        }

        return $sql;
    }

    private function rewriteAddIndex(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+ADD (UNIQUE |)INDEX\s+([^\s]+)\s+\(((?:[^\(\)]+|\([^\(\)]+\))+)\)/';

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

            // Add backticks around index name and column name, and include IF NOT EXISTS clause
            $sql = "CREATE {$unique}INDEX IF NOT EXISTS `{$index}` ON `{$table}` (`{$columns}`)";
        }

        return $sql;
    }

    private function rewriteChangeColumn(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+CHANGE COLUMN\s+([^\s]+)\s+([^\s]+)\s+([^ ]+)( unsigned|)\s*(NOT NULL|)\s*(default (.+)|)/';

        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $newname = $matches[3];
            $type = strtolower($matches[4]);
            if(isset($this->stringReplacements[$type])) {
                $type = $this->stringReplacements[$type];
            }
            $unsigned = $matches[5];
            $notnull = $matches[6];
            $default = $matches[7];
            $defval = $matches[8];
            if(isset($this->stringReplacements[$defval])) {
                $defval = $this->stringReplacements[$defval];
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

        return $sql;
    }

    private function rewriteAlterColumn(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+ALTER COLUMN\s+/';

        if(1 === preg_match($pattern, $sql)) {
            // Translate default values
            $sql = str_replace(
                array_keys($this->stringReplacements),
                array_values($this->stringReplacements),
                $sql
            );
        }

        return $sql;
    }

    private function rewriteAddColumn(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+ADD COLUMN\s+([^\s]+)\s+([^ ]+)( unsigned|)\s+(NOT NULL|)\s*(default (.+)|)/';

        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $col = $matches[2];
            $type = strtolower($matches[3]);
            if(isset($this->stringReplacements[$type])) {
                $type = $this->stringReplacements[$type];
            }
            $unsigned = $matches[4];
            $notnull = $matches[5];
            $default = $matches[6];
            $defval = $matches[7];
            if(isset($this->stringReplacements[$defval])) {
                $defval = $this->stringReplacements[$defval];
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

        return $sql;
    }

    private function rewriteAddKey(string $sql): string
    {
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

        return $sql;
    }

    private function rewriteDropIndex(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+DROP INDEX\s+([^\s]+)/';

        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $index = $matches[2];
            $sql = "DROP INDEX {$table}_{$index}";
        }

        return $sql;
    }

    private function rewriteDropPrimaryKey(string $sql): string
    {
        $pattern = '/ALTER TABLE\s+(\w+)\s+DROP PRIMARY KEY/';

        if(1 === preg_match($pattern, $sql, $matches)) {
            $table = $matches[1];
            $sql = "ALTER TABLE {$table} DROP CONSTRAINT {$table}_pkey";
        }

        return $sql;
    }

    private function rewrite_numeric_type($sql)
    {
        // Numeric types in MySQL which need to be rewritten
        $numeric_types = ["bigint", "int", "integer", "smallint", "mediumint", "tinyint", "double", "decimal"];
        $numeric_types_imploded = implode('|', $numeric_types);

        // Prepare regex pattern to match 'type(x)'
        $pattern = "/(" . $numeric_types_imploded . ")\(\d+\)/";

        // Execute type find & replace
        $sql = preg_replace_callback($pattern, function ($matches) {
            return $matches[1];
        }, $sql);

        // bigint
        $pattern = '/bigint(\(\d+\))?([ ]*NOT NULL)?[ ]*auto_increment/i';
        preg_match($pattern, $sql, $matches);
        if($matches) {
            $sql = preg_replace($pattern, 'bigserial', $sql);
        }

        // int
        $pattern = '/int(\(\d+\))?([ ]*NOT NULL)?[ ]*auto_increment/i';
        preg_match($pattern, $sql, $matches);
        if($matches) {
            $sql = preg_replace($pattern, 'serial', $sql);
        }

        // smallint
        $pattern = '/smallint(\(\d+\))?([ ]*NOT NULL)?[ ]*auto_increment/i';
        preg_match($pattern, $sql, $matches);
        if($matches) {
            $sql = preg_replace($pattern, 'smallserial', $sql);
        }

        // Handle for numeric and decimal -- being replaced with serial
        $numeric_patterns = ['/numeric(\(\d+\))?([ ]*NOT NULL)?[ ]*auto_increment/i', '/decimal(\(\d+\))?([ ]*NOT NULL)?[ ]*auto_increment/i'];
        foreach($numeric_patterns as $pattern) {
            preg_match($pattern, $sql, $matches);
            if($matches) {
                $sql = preg_replace($pattern, 'serial', $sql);
            }
        }

        return $sql;
    }

    private function rewrite_columns_with_protected_names($sql) 
    {
        // Splitting the SQL statement into parts before "(", inside "(", and after ")"
        if (preg_match('/^(CREATE TABLE IF NOT EXISTS|CREATE TABLE|ALTER TABLE)\s+([^\s]+)\s*\((.*)\)(.*)$/is', $sql, $matches)) {
            $prefix = $matches[1] . ' ' . $matches[2] . ' (';
            $columnsAndKeys = $matches[3];
            $suffix = ')' . $matches[4];
    
            $regex = '/(?:^|\s*,\s*)(\b(?:timestamp|date|time|default)\b)\s*(?=\s+\w+)/i'; 

            // Callback function to add quotes around protected column names
            $callback = function($matches) {
                $whitespace = str_replace($matches[1], "", $matches[0]);
                return $whitespace . '"' . $matches[1] . '"';
            };

            // Replace protected column names with quoted versions within columns and keys part
            $columnsAndKeys = preg_replace_callback($regex, $callback, $columnsAndKeys);
            return $prefix . $columnsAndKeys . $suffix;
        }

        return $sql;
    }
}
