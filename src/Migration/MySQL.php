<?php

namespace atk4\schema\Migration;

class MySQL extends \atk4\schema\Migration
{
    public $primary_key_expr = 'integer primary key auto_increment';

    public function describeTable($table) {
        if (!$this->connection->expr('show tables like []', [$table])->get()) {
            return []; // no such table
        }

        $result = [];

        foreach ($this->connection->expr('describe {}', [$table]) as $row) {
            $row2 = [];
            $row2['name'] = $row['Field'];
            $row2['pk'] = $row['Key'] == 'PRI';
            $row2['type'] = preg_replace('/\(.*/','', $row['Type']);

            $result[] = $row2;
        }

        return $result;
    }
}
