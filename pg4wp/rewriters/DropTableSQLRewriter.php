<?php

class DropTableSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();

        $pattern = '/DROP TABLE.+ [`]?(\w+)[`]?$/';
        preg_match($pattern, $sql, $matches);
        $table = $matches[1];
        $seq = $table . '_seq';
        $sql .= ";\nDROP SEQUENCE IF EXISTS $seq;";

        return $sql;
    }
}
