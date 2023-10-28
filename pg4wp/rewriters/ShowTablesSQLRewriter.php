<?php

class ShowTablesSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        return 'SELECT tablename FROM pg_tables WHERE schemaname = \'public\';';
    }
}
