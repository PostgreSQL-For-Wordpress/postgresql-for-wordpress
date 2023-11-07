<?php

class ShowIndexSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();
        $table = $this->extractTableNameFromShowIndex($sql);
        return $this->generatePostgresShowIndexFrom($table);
    }

    /**
     * Extracts table name from a "SHOW FULL COLUMNS" SQL statement.
     *
     * @param string $sql The SQL statement
     * @return string|null The table name if found, or null otherwise
     */
    protected function extractVariableName($sql)
    {
        $pattern = "/SHOW INDEX FROM ['\"`]?([^'\"`]+)['\"`]?/i";
        if (preg_match($pattern, $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generates a PostgreSQL-compatible SQL query to mimic MySQL's "SHOW INDEX FROM".
     *
     * @param string $tableName The table name
     * @return string The generated SQL query
     */
    public function generatePostgresShowIndexFrom($tableName)
    {
        $sql = <<<SQL
        SELECT bc.relname AS "Table",
            CASE WHEN i.indisunique THEN '0' ELSE '1' END AS "Non_unique",
            CASE WHEN i.indisprimary THEN 'PRIMARY' WHEN bc.relname LIKE '%usermeta' AND ic.relname = 'umeta_key'
                THEN 'meta_key' ELSE REPLACE( ic.relname, '' . $table . '_', '') END AS "Key_name",
            a.attname AS "Column_name",
            NULL AS "Sub_part"
        FROM pg_class bc, pg_class ic, pg_index i, pg_attribute a
        WHERE bc.oid = i.indrelid
            AND ic.oid = i.indexrelid
            AND (i.indkey[0] = a.attnum OR i.indkey[1] = a.attnum OR i.indkey[2] = a.attnum OR i.indkey[3] = a.attnum OR i.indkey[4] = a.attnum OR i.indkey[5] = a.attnum OR i.indkey[6] = a.attnum OR i.indkey[7] = a.attnum)
            AND a.attrelid = bc.oid
            AND bc.relname = '' . $tableName . ''
            ORDER BY "Key_name", CASE a.attnum
                WHEN i.indkey[0] THEN 0
                WHEN i.indkey[1] THEN 1
                WHEN i.indkey[2] THEN 2
                WHEN i.indkey[3] THEN 3
                WHEN i.indkey[4] THEN 4
                WHEN i.indkey[5] THEN 5
                WHEN i.indkey[6] THEN 6
                WHEN i.indkey[7] THEN 7
            END
        SQL;

        return $sql;
    }
}
