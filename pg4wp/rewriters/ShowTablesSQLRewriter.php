<?php

class ShowTablesSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $schema = "public";
        return 'SELECT tablename FROM pg_tables WHERE schemaname = \'$schema\';';
    }
}
