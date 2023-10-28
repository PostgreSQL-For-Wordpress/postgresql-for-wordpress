<?php

abstract class AbstractSQLRewriter
{
    protected string $originalSQL;

    public function __construct(string $sql)
    {
        $this->originalSQL = $sql;
    }

    abstract public function rewrite(): string;

    public function original(): string
    {
        return $this->originalSQL;
    }

    public function type(): string
    {
        // Get the called class name and remove the "SQLRewriter" suffix to get the SQL type
        $className = get_called_class();
        $type = str_replace('SQLRewriter', '', $className);
        return $type;
    }
}
