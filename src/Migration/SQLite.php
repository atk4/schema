<?php

namespace atk4\schema\Migration;

class SQLite extends \atk4\schema\Migration
{
    public $primary_key_expr = 'integer primary key autoincrement';

    public function describeTable($table) {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }
}
