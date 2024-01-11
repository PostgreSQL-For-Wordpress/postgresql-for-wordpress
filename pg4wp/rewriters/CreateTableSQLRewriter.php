<?php

class CreateTableSQLRewriter extends AbstractSQLRewriter
{
    private $stringReplacements = [
        ' bigint(40)'   => ' bigint',
        ' bigint(20)'	=> ' bigint',
        ' bigint(10)'	=> ' int',
        ' int(11)'		=> ' int',
        ' int(10)'      => ' int',
        ' int(1)'		=> ' smallint',
        ' tinytext'		=> ' text',
        ' mediumtext'	=> ' text',
        ' longtext'		=> ' text',
        ' unsigned'		=> ' ',
        'gmt datetime NOT NULL default \'0000-00-00 00:00:00\''	=> 'gmt timestamp NOT NULL DEFAULT timezone(\'gmt\'::text, now())',
        'default \'0000-00-00 00:00:00\''	=> 'DEFAULT now()',
        '\'0000-00-00 00:00:00\''	=> 'now()',
        'datetime'		=> 'timestamp',
        ' DEFAULT CHARACTER SET utf8mb4' => '',
        ' DEFAULT CHARACTER SET utf8'	=> '',

        // WP 2.7.1 compatibility
        ' int(4)'		=> ' smallint',

        // For WPMU (starting with WP 3.2)
        ' tinyint(2)'	=> ' smallint',
        ' tinyint(1)'	=> ' smallint',
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
                $sql .= "\nCREATE {$unique}INDEX $indexName ON $table ($columns);";
            }
        }
        // Now remove handled indexes
        $sql = preg_replace($pattern, '', $sql);


        $pattern = "/(,\s*)?UNIQUE KEY\s+[a-zA-Z0-9_]+\s+(\([a-zA-Z0-9_,\s]+\))/";
        $replacement = "$1UNIQUE $2";
        $sql = preg_replace($pattern, $replacement, $sql);

        return $sql;
    }
}
