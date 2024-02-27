<?php

class ShowFullColumnsSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();
        $table = $this->extractTableNameFromShowColumns($sql);
        return $this->generatePostgresShowColumns($table);
    }

    /**
     * Extracts table name from a "SHOW FULL COLUMNS" SQL statement.
     *
     * @param string $sql The SQL statement
     * @return string|null The table name if found, or null otherwise
     */
    protected function extractTableNameFromShowColumns($sql)
    {
        $pattern = "/SHOW FULL COLUMNS FROM ['\"`]?([^'\"`]+)['\"`]?/i";
        if (preg_match($pattern, $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generates a PostgreSQL-compatible SQL query to mimic MySQL's "SHOW FULL COLUMNS".
     *
     * @param string $tableName The table name
     * @param string $schema The schema name
     * @return string The generated SQL query
     */
    public function generatePostgresShowColumns($tableName, $schema = "public")
    {
        $sql = <<<SQL
            SELECT 
                a.attname AS "Field",
                pg_catalog.format_type(a.atttypid, a.atttypmod) AS "Type",
                (CASE 
                    WHEN a.attnotnull THEN 'NO' 
                    ELSE 'YES' 
                END) AS "Null",
                (CASE 
                    WHEN i.indisprimary THEN 'PRI'
                    WHEN i.indisunique THEN 'UNI'
                    ELSE '' 
                END) AS "Key",
                pg_catalog.pg_get_expr(ad.adbin, ad.adrelid) AS "Default",
                '' AS "Extra",
                'select,insert,update,references' AS "Privileges",
                d.description AS "Comment"
            FROM 
                pg_catalog.pg_attribute a
                LEFT JOIN pg_catalog.pg_description d ON (a.attrelid = d.objoid AND a.attnum = d.objsubid)
                LEFT JOIN pg_catalog.pg_attrdef ad ON (a.attrelid = ad.adrelid AND a.attnum = ad.adnum)
                LEFT JOIN pg_catalog.pg_index i ON (a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey))
            WHERE 
                a.attnum > 0 
                AND NOT a.attisdropped
                AND a.attrelid = (
                    SELECT c.oid
                    FROM pg_catalog.pg_class c
                    LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.relname = '$tableName'
                    AND n.nspname = '$schema'
                )
            ORDER BY 
                a.attnum;
        SQL;

        return $sql;
    }
}
