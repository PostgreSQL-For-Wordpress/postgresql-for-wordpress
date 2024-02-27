<?php

class ReplaceIntoSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

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
            $insertIndex = strpos($statement, 'REPLACE INTO');
            $columnsStartIndex = strpos($statement, "(");
            $columnsEndIndex = strpos($statement, ")");
            $valuesIndex = strpos($statement, 'VALUES');
            $onDuplicateKeyIndex = strpos($statement, 'ON DUPLICATE KEY UPDATE');

            // Extract SQL components
            $tableSection = trim(substr($statement, $insertIndex, $columnsStartIndex - $insertIndex));
            $valuesSection = trim(substr($statement, $valuesIndex, strlen($statement) - $valuesIndex));
            $columnsSection = trim(substr($statement, $columnsStartIndex, $columnsEndIndex - $columnsStartIndex + 1));

            // Extract and clean up column names from the update section
            $updateCols = explode(',', substr($columnsSection, 1, strlen($columnsSection) - 2));
            $updateCols = array_map(function ($col) {
                return  trim($col);
            }, $updateCols);

            // Choose a primary key for ON CONFLICT
            $primaryKey = 'option_name';
            if (!in_array($primaryKey, $updateCols)) {
                $primaryKey = 'meta_name';
                if (!in_array($primaryKey, $updateCols)) {
                    $primaryKey = $updateCols[0] ?? '';
                }
            }

            // SWAP REPLACE INTO for INSERT INTO
            $tableSection = str_replace("REPLACE INTO", "INSERT INTO", $tableSection);

            // Construct the PostgreSQL ON CONFLICT DO UPDATE section
            $updateSection = "";
            foreach($updateCols as $col) {
                if ($col !== $primaryKey) {
                    $updateSection .= ", ";
                    $updateSection .= "$col = EXCLUDED.$col";
                }
            }

            // trim any preceding commas
            $updateSection = ltrim($updateSection, ", ");

            // Construct the PostgreSQL query
            $postgresSQL = sprintf('%s %s %s ON CONFLICT (%s) DO UPDATE SET %s', $tableSection, $columnsSection, $valuesSection, $primaryKey, $updateSection);

            if(false === strpos($postgresSQL, 'RETURNING')) {
                $end_of_statement = $this->findSemicolon($postgresSQL);
                if ($end_of_statement !== false) {
                    // Create the substrings up to and after the semicolon
                    $sql_before_semicolon = substr($postgresSQL, 0, $end_of_statement);
                    $sql_after_semicolon = substr($postgresSQL, $end_of_statement, strlen($postgresSQL));
    
                    // Splice the SQL string together with 'RETURNING *'
                    $postgresSQL = $sql_before_semicolon . ' RETURNING *' . $sql_after_semicolon;
    
                } else {
                    $postgresSQL = $postgresSQL .= " RETURNING *";
                }
            }

            // Append to the converted statements list
            $convertedStatements[] = $postgresSQL;
        }

        $sql = implode('; ', $convertedStatements);

        return $sql;
    }

      // finds semicolons that aren't in variables
      private function findSemicolon($sql)
      {
          $quoteOpened = false;
          $parenthesisDepth = 0;
  
          $sqlAsArray = str_split($sql);
          for($i = 0; $i < count($sqlAsArray); $i++) {
              if(($sqlAsArray[$i] == '"' || $sqlAsArray[$i] == "'") && ($i == 0 || $sqlAsArray[$i - 1] != '\\')) {
                  $quoteOpened = !$quoteOpened;
              } elseif($sqlAsArray[$i] == '(' && !$quoteOpened) {
                  $parenthesisDepth++;
              } elseif($sqlAsArray[$i] == ')' && !$quoteOpened) {
                  $parenthesisDepth--;
              } elseif($sqlAsArray[$i] == ';' && !$quoteOpened && $parenthesisDepth == 0) {
                  return $i;
              }
          }
          return false;
      }
}
