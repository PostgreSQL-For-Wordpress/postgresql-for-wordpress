<?php

class ShowVariablesSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();
        $table = $this->extractVariableName($sql);
        return $this->generatePostgres($sql, $variableName);
    }

    /**
     * Extracts table name from a "SHOW FULL COLUMNS" SQL statement.
     *
     * @param string $sql The SQL statement
     * @return string|null The table name if found, or null otherwise
     */
    protected function extractVariableName($sql)
    {
        $pattern = "/SHOW VARIABLES LIKE ['\"`]?([^'\"`]+)['\"`]?/i";
        if (preg_match($pattern, $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generates a PostgreSQL-compatible SQL query to mimic MySQL's "SHOW VARIABLES".
     *
     * @param string $tableName The table name
     * @return string The generated SQL query
     */
    public function generatePostgres($sql, $variableName)
    {
        if ($variableName == "sql_mode") {
            // Act like MySQL default configuration, where sql_mode is ""
            return "SELECT '$variableName' AS \"Variable_name\", '' AS \"Value\";";
        }

        // return untransformed sql
        return $sql;
    }
}
