<?php

class ShowTableStatusSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();
        return $this->generatePostgresShowTableStatus();
    }


    /**
     * Generates a PostgreSQL-compatible SQL query to mimic MySQL's "SHOW TABLE STATUS".
     *
     * @return string The generated SQL query
     */
    public function generatePostgresShowTableStatus($schema = "public")
    {
        $sql = <<<SQL
        SELECT 
            'Postgres' AS Engine,
            cls.relname AS TableName, 
            NULL AS Version,
            NULL AS Row_format,
            cls.reltuples AS Rows,
            NULL AS Avg_row_length,
            pg_size_pretty(pg_relation_size(cls.oid)) AS Data_length,
            NULL AS Max_data_length,
            pg_size_pretty(pg_indexes_size(cls.oid)) AS Index_length, 
            NULL AS Data_free,
            NULL AS Auto_increment,
            NULL AS Create_time,
            NULL AS Update_time,
            NULL AS Check_time,
            'UTF8' AS Table_collation,
            NULL AS Checksum,
            NULL AS Create_options,
            obj_description(cls.oid) AS Comment
        FROM
            pg_class cls
        JOIN 
            pg_namespace nsp ON cls.relnamespace = nsp.oid
        WHERE 
            cls.relkind = 'r' 
            AND nsp.nspname NOT LIKE 'pg_%' 
            AND nsp.nspname != 'information_schema' 
            AND nsp.nspname = '$schema' 
        ORDER BY 
            cls.relname ASC;
        SQL;

        return $sql;
    }
}
