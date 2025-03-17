<?php

class DeleteSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

        // ORDER BY is not supported in DELETE queries, and not required
        // when LIMIT is not present
        if(false !== strpos($sql, 'ORDER BY') && false === strpos($sql, 'LIMIT')) {
            $pattern = '/ORDER BY \S+ (ASC|DESC)?/';
            $sql = preg_replace($pattern, '', $sql);
        }

        // LIMIT is not allowed in DELETE queries
        $sql = str_replace('LIMIT 1', '', $sql);
        $sql = str_replace(' REGEXP ', ' ~ ', $sql);

        // Get the WordPress table prefix
        $prefix = $wpdb->prefix;

        // This handles removal of duplicate entries in table options
        if(false !== strpos($sql, 'DELETE o1 FROM ')) {
            $sql = "DELETE FROM $wpdb->options WHERE option_id IN " .
                "(SELECT o1.option_id FROM $wpdb->options AS o1, $wpdb->options AS o2 " .
                "WHERE o1.option_name = o2.option_name " .
                "AND o1.option_id < o2.option_id)";
        }
        // Rewrite _transient_timeout multi-table delete query with dynamic prefix for options table
        elseif(preg_match('/DELETE a, b FROM ' . preg_quote($prefix, '/') . 'options a, ' . preg_quote($prefix, '/') . 'options b/', $sql)) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.option_value', 'AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            $sql = "DELETE FROM {$wpdb->options} a USING {$wpdb->options} b WHERE " .
                '(' . $where . ') OR (' . $where2 . ');';
        }

        // Rewrite _transient_timeout multi-table delete query with dynamic prefix for sitemeta table
        elseif(preg_match('/DELETE a, b FROM ' . preg_quote($prefix, '/') . 'sitemeta a, ' . preg_quote($prefix, '/') . 'sitemeta b/', $sql)) {
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            // Fix string/number comparison by adding check and cast
            $where = str_replace('AND b.meta_value', 'AND b.meta_value ~ \'^[0-9]+$\' AND CAST(b.meta_value AS BIGINT)', $where);
            // Mirror WHERE clause to delete both sides of self-join.
            $where2 = strtr($where, array('a.' => 'b.', 'b.' => 'a.'));
            // Use $wpdb's sitemeta table name which should already have the correct prefix
            if(isset($wpdb->sitemeta)) {
                $sql = "DELETE FROM {$wpdb->sitemeta} a USING {$wpdb->sitemeta} b WHERE " .
                    '(' . $where . ') OR (' . $where2 . ');';
            } else {
                // Fallback if $wpdb->sitemeta is not available
                $sql = "DELETE FROM {$prefix}sitemeta a USING {$prefix}sitemeta b WHERE " .
                    '(' . $where . ') OR (' . $where2 . ');';
            }
        }
        
        // Add a more general pattern to handle multi-table DELETE with aliases and dynamic table names
        elseif(preg_match('/DELETE\s+([a-zA-Z0-9_]+),\s*([a-zA-Z0-9_]+)\s+FROM\s+([a-zA-Z0-9_' . preg_quote($prefix, '/') . ']+)\s+([a-zA-Z0-9_]+),\s*([a-zA-Z0-9_' . preg_quote($prefix, '/') . ']+)\s+([a-zA-Z0-9_]+)\s+WHERE/i', $sql, $matches)) {
            // Extract aliases and table names
            $firstAlias = $matches[1];
            $secondAlias = $matches[2];
            $firstTable = $matches[3];
            $firstTableAlias = $matches[4];
            $secondTable = $matches[5];
            $secondTableAlias = $matches[6];
            
            // Extract WHERE clause
            $where = substr($sql, strpos($sql, 'WHERE ') + 6);
            $where = rtrim($where, " \t\n\r;");
            
            // Check if the table names are known WordPress tables and replace with dynamic property references
            foreach([$firstTable, $secondTable] as $index => $tableName) {
                // Strip prefix if it exists to get the base table name
                $baseTableName = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $tableName);
                
                // Check if $wpdb has a property for this table
                if(isset($wpdb->$baseTableName)) {
                    // Replace the hardcoded table name with the dynamic property
                    if($index === 0) {
                        $firstTable = $wpdb->$baseTableName;
                    } else {
                        $secondTable = $wpdb->$baseTableName;
                    }
                }
            }
            
            // Generate PostgreSQL DELETE...USING syntax
            $sql = "DELETE FROM $firstTable $firstTableAlias USING $secondTable $secondTableAlias WHERE $where;";
        }

        // Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
        if(false !== strpos($sql, $wpdb->comments)) {
            $sql = str_replace(' comment_id ', ' comment_ID ', $sql);
        }

        return $sql;
    }
}
