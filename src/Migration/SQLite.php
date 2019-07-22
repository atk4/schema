<?php

namespace atk4\schema\Migration;

class SQLite extends \atk4\schema\Migration
{
    /** @var string Expression to create primary key */
    public $primary_key_expr = 'integer primary key autoincrement';

    public $mapToAgile = [
        0 => ['string'],
    ];
    /**
     * Return database table descriptions.
     * DB engine specific.
     *
     * @param string $table
     *
     * @return array
     */
    public function describeTable(string $table) : array
    {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }
}
