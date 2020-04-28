<?php

namespace atk4\schema\Migration;

// ONLY PARTIALLY IMPLEMENTED
class PgSQL extends \atk4\schema\Migration
{
    /** @var string Expression to create primary key */
    public $primary_key_expr = 'generated by default as identity(start with 1) primary key';

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [
        'boolean' => ['boolean'],
        'date' => ['date'],
        'datetime' => ['timestamp'], // without timezone
        'time' => ['time'], // without timezone
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [
        'boolean' => ['boolean'],
        'date' => ['date'],
        'datetime' => ['datetime'],
        'timestamp' => ['datetime'],
        'time' => ['time'],
    ];

    /**
     * Return database table descriptions.
     * DB engine specific.
     */
    public function describeTable(string $table): array
    {
        $columns = $this->connection->expr('SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = []', [$table])->get();

        if (!$columns) {
            return []; // no such table
        }

        $result = [];

        foreach ($columns as $row) {
            $row2 = [];
            $row2['name'] = $row['column_name'];
            $row2['pk'] = $row['is_identity'] === 'YES';
            $row2['type'] = preg_replace('/\(.*/', '', $row['udt_name']); // $row['data_type'], but it's PgSQL specific type

            $result[] = $row2;
        }

        return $result;
    }

    /**
     * Renders statement.
     */
    public function _render_statements(): string
    {
        $result = [];

        if (isset($this->args['dropField'])) {
            foreach ($this->args['dropField'] as $field => $junk) {
                $result[] = 'drop column ' . $this->_escape($field);
            }
        }

        if (isset($this->args['newField'])) {
            foreach ($this->args['newField'] as $field => $option) {
                $result[] = 'add column ' . $this->_render_one_field($field, $option);
            }
        }

        if (isset($this->args['alterField'])) {
            foreach ($this->args['alterField'] as $field => $option) {
                $type = $this->getSQLFieldType($option['type'] ?? null, $option);
                $result[] = 'alter column ' . $this->_escape($field) .
                                ' type ' . $type .
                                ' using (' . $this->_escape($field) . '::' . $type . ')'; // requires to cast value
            }
        }

        return implode(', ', $result);
    }
}
