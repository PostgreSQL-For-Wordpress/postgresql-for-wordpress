<?php

class SetNamesSQLRewriter extends AbstractSQLRewriter
{
    public function rewrite(): string
    {
        return "SET NAMES 'utf8'";
    }
}
