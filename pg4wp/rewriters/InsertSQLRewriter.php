<?php

class InsertSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

        $sql = str_replace('(0,', "('0',", $sql);
        $sql = str_replace('(1,', "('1',", $sql);

        // Fix inserts into wp_categories
        if(false !== strpos($sql, 'INSERT INTO ' . $wpdb->categories)) {
            $sql = str_replace('"cat_ID",', '', $sql);
            $sql = str_replace("VALUES ('0',", "VALUES(", $sql);
        }

        // Those are used when we need to set the date to now() in gmt time
        $sql = str_replace("'0000-00-00 00:00:00'", 'now() AT TIME ZONE \'gmt\'', $sql);

        // Multiple values group when calling INSERT INTO don't always work
        if(false !== strpos($sql, $wpdb->options) && false !== strpos($sql, '), (')) {
            $pattern = '/INSERT INTO.+VALUES/';
            preg_match($pattern, $sql, $matches);
            $insert = $matches[0];
            $sql = str_replace('), (', ');' . $insert . '(', $sql);
        }

        // Swap ON DUPLICATE KEY SYNTAX
        if(false !== $pos = strpos($sql, 'ON DUPLICATE KEY UPDATE')) {
            $splitStatements = function (string $sql): array {
                $statements = [];
                $buffer = '';
                $quote = null;

                for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
                    $char = $sql[$i];

                    if ($quote) {
                        if ($char === $quote && $sql[$i - 1] !== '\\') {
                            $quote = null;
                        }
                    } elseif ($char === '"' || $char === "'") {
                        $quote = $char;
                    } elseif ($char === ';') {
                        $statements[] = $buffer . ';';
                        $buffer = '';
                        continue;
                    }

                    $buffer .= $char;
                }

                if (!empty($buffer)) {
                    $statements[] = $buffer;
                }

                return $statements;
            };

            $statements = $splitStatements($sql);

            foreach ($statements as $statement) {
                $statement = trim($statement);

                // Skip empty statements
                if (empty($statement)) {
                    continue;
                }

                // Replace backticks with double quotes for PostgreSQL compatibility
                $statement = str_replace('`', '"', $statement);

                // Find index positions for the SQL components
                $insertIndex = strpos($statement, 'INSERT INTO');
                $valuesIndex = strpos($statement, 'VALUES');
                $onDuplicateKeyIndex = strpos($statement, 'ON DUPLICATE KEY UPDATE');

                // Extract SQL components
                $tableSection = trim(substr($statement, $insertIndex, $valuesIndex - $insertIndex));
                $valuesSection = trim(substr($statement, $valuesIndex, $onDuplicateKeyIndex - $valuesIndex));
                $updateSection = trim(str_replace('ON DUPLICATE KEY UPDATE', '', substr($statement, $onDuplicateKeyIndex)));

                // Extract and clean up column names from the update section
                $updateCols = explode(',', $updateSection);
                $updateCols = array_map(function ($col) {
                    return trim(explode('=', $col)[0]);
                }, $updateCols);

                // Choose a primary key for ON CONFLICT
                $primaryKey = 'option_name';
                if (!in_array($primaryKey, $updateCols)) {
                    $primaryKey = 'meta_name';
                    if (!in_array($primaryKey, $updateCols)) {
                        $primaryKey = $updateCols[0] ?? '';
                    }
                }

                // Construct the PostgreSQL ON CONFLICT DO UPDATE section
                $updateSection = implode(', ', array_map(fn($col) => "$col = EXCLUDED.$col", $updateCols));

                // Construct the PostgreSQL query
                $postgresSQL = sprintf('%s %s ON CONFLICT (%s) DO UPDATE SET %s', $tableSection, $valuesSection, $primaryKey, $updateSection);

                // Append to the converted statements list
                $convertedStatements[] = $postgresSQL;
            }

            $sql = implode('; ', $convertedStatements);
        } elseif(0 === strpos($sql, 'INSERT IGNORE')) {
            // Note: Requires PostgreSQL 9.5
            $sql = 'INSERT' . substr($sql, 13) . ' ON CONFLICT DO NOTHING';
        }

        // To avoid Encoding errors when inserting data coming from outside
        if(preg_match('/^.{1}/us', $sql, $ar) != 1) {
            $sql = utf8_encode($sql);
        }

        return $sql;
    }
}
