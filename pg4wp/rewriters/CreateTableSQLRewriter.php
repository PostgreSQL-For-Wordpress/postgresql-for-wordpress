<?php

class CreateTableSQLRewriter extends AbstractSQLRewriter
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

        $tableSQL = str_replace('CREATE TABLE IF NOT EXISTS ', 'CREATE TABLE ', $sql);
        $pattern = '/CREATE TABLE [`]?(\w+)[`]?/';
        preg_match($pattern, $tableSQL, $matches);
        $table = $matches[1];

        // change all creates into create if not exists
        $pattern = "/CREATE TABLE (IF NOT EXISTS )?(\w+)\s*\(/i";
        $replacement = 'CREATE TABLE IF NOT EXISTS $2 (';
        $sql = preg_replace($pattern, $replacement, $sql);

        // Remove trailing spaces
        $sql = trim($sql);

        // Add a slash if needed
        if (substr($sql, strlen($sql) - 1, 1) != ";") {
            $sql = $sql . ";";
        }

        // Translate types and some other replacements
        $sql = str_ireplace(
            array_keys($this->stringReplacements),
            array_values($this->stringReplacements),
            $sql
        );

        $sql = $this->rewrite_numeric_type($sql);

        // Support for UNIQUE INDEX creation
        $pattern = '/,\s*(UNIQUE |)KEY\s+(`[^`]+`|\w+)\s+\(((?:[^()]|\([^)]*\))*)\)/';
        if(preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {
                $unique = $match[1];
                $index = $match[2];
                $columns = $match[3];

                // Removing backticks from the index names
                $index = str_replace('`', '', $index);

                // Removing backticks and key length constraints from the columns
                $columns = preg_replace(["/`/", "/\(\d+\)/"], '', $columns);

                // Creating a unique index name
                $indexName = $table . '_' . $index;

                // Appending the CREATE INDEX statement to SQL
                $sql .= "\nCREATE {$unique}INDEX IF NOT EXISTS $indexName ON $table ($columns);";
            }
        }
        // Now remove handled indexes
        $sql = preg_replace($pattern, '', $sql);


        $pattern = "/(,\s*)?UNIQUE KEY\s+[a-zA-Z0-9_]+\s+(\([a-zA-Z0-9_,\s]+\))/";
        $replacement = "$1UNIQUE $2";
        $sql = preg_replace($pattern, $replacement, $sql);

        return $sql;
    }

    private function rewrite_numeric_type($sql){
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
}
