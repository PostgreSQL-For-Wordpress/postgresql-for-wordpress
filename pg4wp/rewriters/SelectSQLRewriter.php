<?php

class SelectSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        global $wpdb;

        $sql = $this->original();

        // SQL_CALC_FOUND_ROWS doesn't exist in PostgreSQL but it's needed for correct paging
        if(false !== strpos($sql, 'SQL_CALC_FOUND_ROWS')) {
            $sql = str_replace('SQL_CALC_FOUND_ROWS', '', $sql);
            $GLOBALS['pg4wp_numrows_query'] = $sql;
            if(PG4WP_DEBUG) {
                error_log('[' . microtime(true) . "] Number of rows required for :\n$sql\n---------------------\n", 3, PG4WP_LOG . 'pg4wp_NUMROWS.log');
            }
        }

        if(false !== strpos($sql, 'FOUND_ROWS()')) {
            // Here we convert the latest query into a COUNT query
            $sql = $GLOBALS['pg4wp_numrows_query'];

            // Remove the LIMIT clause if it exists
            $sql = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?/i', '', $sql);

            // Remove the ORDER BY clause if it exists
            $sql = preg_replace('/\s+ORDER\s+BY\s+[^)]+/i', '', $sql);

            // Replace the fields in the SELECT clause with COUNT(*)
            $sql = preg_replace('/SELECT\s+.*?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql, 1);
        }

        $sql = $this->ensureOrderByInSelect($sql);

        // Convert CONVERT to CAST
        $pattern = '/CONVERT\(([^()]*(\(((?>[^()]+)|(?-2))*\))?[^()]*),\s*([^\s]+)\)/x';
        $sql = preg_replace($pattern, 'CAST($1 AS $4)', $sql);

        // Handle CAST( ... AS CHAR)
        $sql = preg_replace('/CAST\((.+) AS CHAR\)/', 'CAST($1 AS TEXT)', $sql);

        // Handle CAST( ... AS SIGNED)
        $sql = preg_replace('/CAST\((.+) AS SIGNED\)/', 'CAST($1 AS INTEGER)', $sql);

        // Handle COUNT(*)...ORDER BY...
        $sql = preg_replace('/COUNT(.+)ORDER BY.+/s', 'COUNT$1', $sql);

        // In order for users counting to work...
        $matches = array();
        if(preg_match_all('/COUNT[^C]+\),/', $sql, $matches)) {
            foreach($matches[0] as $num => $one) {
                $sub = substr($one, 0, -1);
                $sql = str_replace($sub, $sub . ' AS count' . $num, $sql);
            }
        }

        $sql = $this->convertToPostgresLimitSyntax($sql);
        $sql = $this->ensureGroupByOrAggregate($sql);

        $pattern = '/DATE_ADD[ ]*\(([^,]+),([^\)]+)\)/';
        $sql = preg_replace($pattern, '($1 + $2)', $sql);

        // Convert MySQL FIELD function to CASE statement
        $pattern = '/FIELD[ ]*\(([^\),]+),([^\)]+)\)/';
        // https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_field
        // Other implementations:  https://stackoverflow.com/q/1309624
        $sql = preg_replace_callback($pattern, function ($matches) {
            $case = 'CASE ' . trim($matches[1]);
            $comparands = explode(',', $matches[2]);
            foreach($comparands as $i => $comparand) {
                $case .= ' WHEN ' . trim($comparand) . ' THEN ' . ($i + 1);
            }
            $case .= ' ELSE 0 END';
            return $case;
        }, $sql);

        $pattern = '/GROUP_CONCAT\(([^()]*(\(((?>[^()]+)|(?-2))*\))?[^()]*)\)/x';
        $sql = preg_replace($pattern, "string_agg($1, ',')", $sql);

        // Convert MySQL RAND function to PostgreSQL RANDOM function
        $pattern = '/RAND[ ]*\([ ]*\)/';
        $sql = preg_replace($pattern, 'RANDOM()', $sql);

        // UNIX_TIMESTAMP in MYSQL returns an integer
        $pattern = '/UNIX_TIMESTAMP\(([^\)]+)\)/';
        $sql = preg_replace($pattern, 'ROUND(DATE_PART(\'epoch\',$1))', $sql);

        $date_funcs = array(
            'DAYOFMONTH('	=> 'EXTRACT(DAY FROM ',
            'YEAR('			=> 'EXTRACT(YEAR FROM ',
            'MONTH('		=> 'EXTRACT(MONTH FROM ',
            'DAY('			=> 'EXTRACT(DAY FROM ',
        );

        $sql = str_replace('ORDER BY post_date DESC', 'ORDER BY YEAR(post_date) DESC, MONTH(post_date) DESC', $sql);
        $sql = str_replace('ORDER BY post_date ASC', 'ORDER BY YEAR(post_date) ASC, MONTH(post_date) ASC', $sql);
        $sql = str_replace(array_keys($date_funcs), array_values($date_funcs), $sql);
        $curryear = date('Y');
        $sql = str_replace('FROM \'' . $curryear, 'FROM TIMESTAMP \'' . $curryear, $sql);

        // MySQL 'IF' conversion - Note : NULLIF doesn't need to be corrected
        $pattern = '/ (?<!NULL)IF[ ]*\(([^,]+),([^,]+),([^\)]+)\)/';
        $sql = preg_replace($pattern, ' CASE WHEN $1 THEN $2 ELSE $3 END', $sql);

        // Act like MySQL default configuration, where sql_mode is ""
        $pattern = '/@@SESSION.sql_mode/';
        $sql = preg_replace($pattern, "''", $sql);

        if(isset($wpdb)) {
            $sql = str_replace('GROUP BY ' . $wpdb->prefix . 'posts.ID', '', $sql);
        }
        $sql = str_replace("!= ''", '<> 0', $sql);

        // MySQL 'LIKE' is case insensitive by default, whereas PostgreSQL 'LIKE' is
        $sql = str_replace(' LIKE ', ' ILIKE ', $sql);

        // INDEXES are not yet supported
        if(false !== strpos($sql, 'USE INDEX (comment_date_gmt)')) {
            $sql = str_replace('USE INDEX (comment_date_gmt)', '', $sql);
        }

        // HB : timestamp fix for permalinks
        $sql = str_replace('post_date_gmt > 1970', 'post_date_gmt > to_timestamp (\'1970\')', $sql);

        // Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
        if(isset($wpdb) && $wpdb->comments && false !== strpos($sql, $wpdb->comments)) {
            $sql = str_replace(' comment_id ', ' comment_ID ', $sql);
        }

        // MySQL treats a HAVING clause without GROUP BY like WHERE
        if(false !== strpos($sql, 'HAVING') && false === strpos($sql, 'GROUP BY')) {
            if(false === strpos($sql, 'WHERE')) {
                $sql = str_replace('HAVING', 'WHERE', $sql);
            } else {
                $pattern = '/WHERE\s+(.*?)\s+HAVING\s+(.*?)(\s*(?:ORDER|LIMIT|PROCEDURE|INTO|FOR|LOCK|$))/';
                $sql = preg_replace($pattern, 'WHERE ($1) AND ($2) $3', $sql);
            }
        }

        // MySQL allows integers to be used as boolean expressions
        // where 0 is false and all other values are true.
        //
        // Although this could occur anywhere with any number, so far it
        // has only been observed as top-level expressions in the WHERE
        // clause and only with 0.  For performance, limit current
        // replacements to that.
        $pattern_after_where = '(?:\s*$|\s+(GROUP|HAVING|ORDER|LIMIT|PROCEDURE|INTO|FOR|LOCK))';
        $pattern = '/(WHERE\s+)0(\s+AND|\s+OR|' . $pattern_after_where . ')/';
        $sql = preg_replace($pattern, '$1false$2', $sql);

        $pattern = '/(AND\s+|OR\s+)0(' . $pattern_after_where . ')/';
        $sql = preg_replace($pattern, '$1false$2', $sql);

        // MySQL supports strings as names, PostgreSQL needs identifiers.
        // Limit to after closing parenthesis to reduce false-positives
        // Currently only an issue for nextgen-gallery plugin
        $pattern = '/\) AS \'([^\']+)\'/';
        $sql = preg_replace($pattern, ') AS "$1"', $sql);

        return $sql;
    }

    /**
     * Ensure the columns used in the ORDER BY clause are also present in the SELECT clause.
     *
     * @param string $sql Original SQL query string.
     * @return string Modified SQL query string.
     */
    protected function ensureOrderByInSelect(string $sql): string
    {
        // Extract the SELECT and ORDER BY clauses
        preg_match('/SELECT\s+(.*?)\s+FROM/si', $sql, $selectMatches);
        preg_match('/ORDER BY(.*?)(ASC|DESC|$)/si', $sql, $orderMatches);
        preg_match('/GROUP BY(.*?)(ASC|DESC|$)/si', $sql, $groupMatches);

        // If the SELECT clause is missing, return the original query
        if (!$selectMatches) {
            return $sql;
        }

        // If both ORDER BY and GROUP BY clauses are missing, return the original query
        if (!$orderMatches && !$groupMatches) {
            return $sql;
        }

        $selectClause = trim($selectMatches[1]);
        $orderByClause = $orderMatches ? trim($orderMatches[1]) : null;
        $groupClause = $groupMatches ? trim($groupMatches[1]) : null;
        $modified = false;

        // Check for wildcard in SELECT
        if (strpos($selectClause, '*') !== false) {
            return $sql; // Cannot handle wildcards, return original query
        }

        // Handle ORDER BY columns
        if ($orderByClause) {
            $orderByColumns = explode(',', $orderByClause);
            foreach ($orderByColumns as $col) {
                $col = trim($col);
                if (strpos($selectClause, $col) === false) {
                    $selectClause .= ', ' . $col;
                    $modified = true;
                }
            }
        }

        // Handle GROUP BY columns
        if ($groupClause && !$modified) {
            $groupColumns = explode(',', $groupClause);
            foreach ($groupColumns as $col) {
                $col = trim($col);
                if (strpos($selectClause, $col) === false) {
                    $selectClause .= ', ' . $col;
                    $modified = true;
                }
            }
        }

        if (!$modified) {
            return $sql;
        }

        // Find the exact position for the replacement
        $selectStartPos = strpos($sql, $selectMatches[1]);
        if ($selectStartPos === false) {
            return $sql; // If for some reason the exact match is not found, return the original query
        }
        $postgresSql = substr_replace($sql, $selectClause, $selectStartPos, strlen($selectMatches[1]));

        return $postgresSql;
    }

    /**
     * Transforms a given SQL query to include a GROUP BY clause if the SELECT statement has both aggregate
     * and non-aggregate columns. This function is specifically designed to work with PostgreSQL.
     *
     * In PostgreSQL, a query that uses aggregate functions must group by all columns in the SELECT list that
     * are not part of the aggregate functions. Failing to do so results in a syntax error. This function
     * automatically adds a GROUP BY clause to meet this PostgreSQL requirement when both aggregate (COUNT, SUM,
     * AVG, MIN, MAX) and non-aggregate columns are present.
     *
     * @param string $sql The SQL query string to be transformed.
     *
     * @return string The transformed SQL query string with appropriate GROUP BY clause if required.
     *
     * @throws Exception If the SQL query cannot be parsed or modified.
     *
     * @example
     * Input:  SELECT COUNT(id), username FROM users;
     * Output: SELECT COUNT(id), username FROM users GROUP BY username;
     *
     */
    protected function ensureGroupByOrAggregate(string $sql): string
    {
        // Check for system or session variables
        if (preg_match('/@@[a-zA-Z0-9_]+/', $sql)) {
            return $sql;
        }

        // Regular expression to capture main SQL components.
        $regex = '/(SELECT\s+)(.*?)(\s+FROM\s+)([^ ]+)(\s+WHERE\s+.*?(?= ORDER BY | GROUP BY | LIMIT |$))?(ORDER BY.*?(?= LIMIT |$))?(LIMIT.*?$)?/is';

        // Capture main SQL components using regex
        if (!preg_match($regex, $sql, $matches)) {
            return $sql;
        }

        $selectClause = trim($matches[2] ?? '');
        $fromClause = trim($matches[4] ?? '');
        $whereClause = trim($matches[5] ?? '');
        $orderClause = trim($matches[6] ?? '');
        $limitClause = trim($matches[7] ?? '');

        if (empty($selectClause) || empty($fromClause)) {
            return $sql;
        }

        // Regular expression to match commas not within parentheses
        $pattern = '/,(?![^\(]*\))/';
        // Split columns using a comma, and then trim each element
        $columns = array_map('trim', preg_split($pattern, $selectClause));

        $aggregateColumns = [];
        $nonAggregateColumns = [];

        foreach ($columns as $col) {
            // Check for aggregate functions in the column
            if (preg_match('/(COUNT|SUM|AVG|MIN|MAX)\s*?\(/i', $col)) {
                $aggregateColumns[] = $col;
            } else {
                $nonAggregateColumns[] = $col;
            }
        }

        // Only add a GROUP BY clause if there are both aggregate and non-aggregate columns in SELECT
        if (empty($aggregateColumns) || empty($nonAggregateColumns)) {
            return $sql;
        }


        // Assemble new SQL query
        $postgresSql = "SELECT $selectClause FROM $fromClause";

        if (!empty($whereClause)) {
            $postgresSql .= ' ' . $whereClause;
        }

        $groupByClause = "GROUP BY " . implode(", ", $nonAggregateColumns);
        if (!empty($groupByClause)) {
            $postgresSql .= ' ' . $groupByClause;
        }

        if (!empty($orderClause)) {
            $postgresSql .= ' ' . $orderClause;
        }

        if (!empty($limitClause)) {
            $postgresSql .= ' ' . $limitClause;
        }

        return $postgresSql;
    }

    /**
     * Convert MySQL LIMIT syntax to PostgreSQL LIMIT syntax
     *
     * @param string $sql MySQL query string
     * @return string PostgreSQL query string
     */
    protected function convertToPostgresLimitSyntax($sql)
    {
        // Use regex to find "LIMIT m, n" syntax in query
        if (preg_match('/LIMIT\s+(\d+),\s*(\d+)/i', $sql, $matches)) {
            $offset = $matches[1];
            $limit = $matches[2];

            // Replace MySQL LIMIT syntax with PostgreSQL LIMIT syntax
            $postgresLimitSyntax = "LIMIT $limit OFFSET $offset";
            $postgresSql = preg_replace('/LIMIT\s+\d+,\s*\d+/i', $postgresLimitSyntax, $sql);

            return $postgresSql;
        }

        // Return original query if no MySQL LIMIT syntax is found
        return $sql;
    }

}
