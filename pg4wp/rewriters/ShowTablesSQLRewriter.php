<?php

class ShowTablesSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        $schema = DB_SCHEMA;
        return 'SELECT tablename FROM pg_tables WHERE schemaname = \'$schema\';';
    }
}
