<?php

class OptimizeTableSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $sql = $this->original();
        return str_replace('OPTIMIZE TABLE', 'VACUUM', $sql);
    }
}
