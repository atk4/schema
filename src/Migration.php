<?php

namespace atk4\schema;

use atk4\core\Exception;
use atk4\dsql\Expression;

class Migration extends Expression
{
    /** @var string Expression mode. See $templates. */
    public $mode = 'create';

    /** @var array Expression templates */
    protected $templates = [
        'create' => 'create table {table} ([field])',
        'drop'   => 'drop table if exists {table}',
        'alter'  => 'alter table {table} [statements]',
        'rename' => 'rename table {old_table} to {table}',
    ];

    /** @var \atk4\dsql\Connection Database connection */
    public $connection;

    /**
     * Field, table and alias name escaping symbol.
     * By SQL Standard it's double quote, but MySQL uses backtick.
     *
     * @var string
     */
    protected $escape_char = '"';

    /** @var string Expression to create primary key */
    public $primary_key_expr = 'integer primary key autoincrement';

    /** @var array Conversion mapping from Agile Data types to persistence types */
    protected $defaultMapToPersistence = [
        ['varchar', 255], // default
        'boolean'   => ['tinyint', 1],
        'integer'   => ['int'],
        'money'     => ['decimal', 12, 2],
        'float'     => ['decimal', 16, 6],
        'date'      => ['date'],
        'datetime'  => ['date'],
        'time'      => ['varchar', 8],
        'text'      => ['text'],
        'array'     => ['text'],
        'object'    => ['text'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [];

    /** @var array Conversion mapping from persistence types to Agile Data types */
    protected $defaultMapToAgile = [
        [null], // default
        'tinyint'   => ['boolean'],
        'int'       => ['integer'],
        'decimal'   => ['float'],
        'numeric'   => ['float'],
        'date'      => ['datetime'],
        'text'      => ['text'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [];

    /**
     * Create new migration.
     *
     * @param \atk4\dsql\Connection|\atk4\data\Persistence|\atk4\data\Model $source
     * @param array                                                         $params
     */
    public function __construct($source, $params = [])
    {
        parent::__construct($params);

        if ($source instanceof \atk4\dsql\Connection) {
            $this->connection = $source;

            return;
        } elseif ($source instanceof \atk4\data\Persistence_SQL || $source instanceof \atk4\data\Persistence\SQL) {
            $this->connection = $source->connection;

            return;
        } elseif ($source instanceof \atk4\data\Model) {
            if ($source->persistence && ($source->persistence instanceof \atk4\data\Persistence_SQL || $source->persistence instanceof \atk4\data\Persistence\SQL)) {
                $this->connection = $source->persistence->connection;

                $this->setModel($source);

                return;
            }
        }

        throw new \atk4\core\Exception([
            'Source is specified incorrectly. Must be Connection, Persistence or initialized Model',
            'source' => $source,
        ]);
    }

    /**
     * Sets model.
     *
     * @param \atk4\data\Model $m
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $m)
    {
        $this->table($m->table);

        foreach ($m->elements as $field) {
            // ignore not persisted model fields
            if (!$field instanceof \atk4\data\Field) {
                continue;
            }

            if ($field->never_persist) {
                continue;
            }

            if ($field instanceof \atk4\data\Field_SQL_Expression) {
                continue;
            }

            if ($field->short_name == $m->id_field) {
                $this->id($field->actual ?: $field->short_name);
                continue;
            }

            $this->field($field->actual ?: $field->short_name, ['type' => $field->type]);  // todo add more options here
        }

        return $m;
    }

    /**
     * Set SQL expression template.
     *
     * @param string $mode Template name
     *
     * @return $this
     */
    public function mode($mode)
    {
        if (!isset($this->templates[$mode])) {
            throw new Exception(['Structure builder does not have this mode', 'mode' => $mode]);
        }

        $this->mode = $mode;
        $this->template = $this->templates[$mode];

        return $this;
    }

    /**
     * Create new table.
     *
     * @return $this
     */
    public function create()
    {
        $this->mode('create')->execute();

        return $this;
    }

    /**
     * Drop table.
     *
     * @return $this
     */
    public function drop()
    {
        $this->mode('drop')->execute();

        return $this;
    }

    /**
     * Alter table.
     *
     * @return $this
     */
    public function alter()
    {
        $this->mode('alter')->execute();

        return $this;
    }

    /**
     * Rename table.
     *
     * @return $this
     */
    public function rename()
    {
        $this->mode('rename')->execute();

        return $this;
    }

    /**
     * Will read current schema and consult current 'field' arguments, to see if they are matched.
     * If table does not exist, will invoke ->create. If table does exist, then it will execute
     * methods ->newField(), ->dropField() or ->alterField() as needed, then call ->alter().
     *
     * @return string Returns short textual info for logging purposes
     */
    public function migrate()
    {
        $changes = $added = $altered = $dropped = 0;

        // We use this to read fields from SQL
        $migration2 = new static($this->connection);

        if (!$migration2->importTable($this['table'])) {
            // should probably use custom exception class here
            $this->create();

            return 'created new table';
        }

        $old = $migration2->_getFields();
        $new = $this->_getFields();

        // add new fields or update existing ones
        foreach ($new as $field => $options) {
            // never update ID field (sadly hard-coded field name)
            if ($field == 'id') {
                continue;
            }

            if (isset($old[$field])) {

                // compare options and if needed alter field
                // @todo add more options here like 'len'
                if (array_key_exists('type', $old[$field]) && array_key_exists('type', $options) && $old[$field]['type'] != $options['type']) {
                    $this->alterField($field, $options);
                    $altered++;
                    $changes++;
                }

                unset($old[$field]);
            } else {
                // new field, so let's just add it
                $this->newField($field, $options);
                $added++;
                $changes++;
            }
        }

        // remaining old fields - drop them
        foreach ($old as $field => $options) {
            // never delete ID field (sadly hard-coded field name)
            if ($field == 'id') {
                continue;
            }

            $this->dropField($field);
            $dropped++;
            $changes++;
        }

        if ($changes) {
            $this->alter();

            return 'added '.$added.' field'.($added % 10 == 1 ? '' : 's').', '.
                'changed '.$altered.' field'.($altered % 10 == 1 ? '' : 's').' and '.
                'deleted '.$dropped.' field'.($dropped % 10 == 1 ? '' : 's');
        }

        return 'no changes';
    }

    /**
     * Renders statement.
     *
     * @return string
     */
    public function _render_statements()
    {
        $result = [];

        if (isset($this->args['dropField'])) {
            foreach ($this->args['dropField'] as $field => $junk) {
                $result[] = 'drop column '.$this->_escape($field);
            }
        }

        if (isset($this->args['newField'])) {
            foreach ($this->args['newField'] as $field => $option) {
                $result[] = 'add column '.$this->_render_one_field($field, $option);
            }
        }

        if (isset($this->args['alterField'])) {
            foreach ($this->args['alterField'] as $field => $option) {
                $result[] = 'change column '.$this->_escape($field).' '.$this->_render_one_field($field, $option);
            }
        }

        return implode(', ', $result);
    }

    /**
     * Create rough model from current set of $this->args['fields']. This is not
     * ideal solution but is designed as a drop-in solution.
     *
     * @param \atk4\data\Persistence $persistence
     * @param string                 $table
     *
     * @return \atk4\data\Model
     */
    public function createModel($persistence, $table = null)
    {
        $m = new \atk4\data\Model([$persistence, 'table'=>$table ?: $this['table'] = $table]);

        foreach ($this->_getFields() as $field => $options) {
            if ($field == 'id') {
                continue;
            }

            if (is_object($options)) {
                continue;
            }

            $defaults = [];

            if ($options['type']) {
                $defaults['type'] = $options['type'];
            }
            $m->addField($field, $defaults);
        }

        return $m;
    }

    /**
     * Sets newField argument.
     *
     * @param string $field
     * @param array  $options
     *
     * @return $this
     */
    public function newField($field, $options = [])
    {
        $this->_set_args('newField', $field, $options);

        return $this;
    }

    /**
     * Sets alterField argument.
     *
     * @param string $field
     * @param array  $options
     *
     * @return $this
     */
    public function alterField($field, $options = [])
    {
        $this->_set_args('alterField', $field, $options);

        return $this;
    }

    /**
     * Sets dropField argument.
     *
     * @param string $field
     *
     * @return $this
     */
    public function dropField($field)
    {
        $this->_set_args('dropField', $field, true);

        return $this;
    }

    /**
     * Return database table descriptions.
     * DB engine specific.
     *
     * @todo Convert to abstract function
     *
     * @param string $table
     *
     * @return array
     */
    public function describeTable($table)
    {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }

    /**
     * Convert SQL field types to Agile Data field types.
     *
     * @param string $type SQL field type
     *
     * @return string|null
     */
    public function getModelFieldType($type)
    {
        // remove parenthesis
        $type = trim(preg_replace('/\(.*/', '', strtolower($type)));

        $map = array_merge($this->defaultMapToAgile, $this->mapToAgile);
        $a = array_key_exists($type, $map) ? $map[$type] : $map[0];

        return $a[0];
    }

    /**
     * Convert Agile Data field types to SQL field types.
     *
     * @param string $type    Agile Data field type
     * @param array  $options More options
     *
     * @return string
     */
    public function getSQLFieldType($type, $options = [])
    {
        $type = strtolower($type);

        $map = array_merge($this->defaultMapToPersistence, $this->mapToPersistence);
        $a = array_key_exists($type, $map) ? $map[$type] : $map[0];

        return $a[0].(count($a) > 1 ? ' ('.implode(',', array_slice($a, 1)).')' : '');
    }

    /**
     * Import fields from database into migration field config.
     *
     * @param string $table
     *
     * @return bool
     */
    public function importTable($table)
    {
        $this->table($table);
        $has_fields = false;
        foreach ($this->describeTable($table) as $row) {
            $has_fields = true;
            if ($row['pk']) {
                $this->id($row['name']);
                continue;
            }

            $type = $this->getModelFieldType($row['type']);

            $this->field($row['name'], ['type'=>$type]);
        }

        return $has_fields;
    }

    /**
     * Sets table name.
     *
     * @param string $table
     *
     * @return $this
     */
    public function table($table)
    {
        $this['table'] = $table;

        return $this;
    }

    /**
     * Sets old table name.
     *
     * @param string $table
     *
     * @return $this
     */
    public function old_table($old_table)
    {
        $this['old_table'] = $old_table;

        return $this;
    }

    /**
     * Add field in template.
     *
     * @param string $name
     * @param array  $options
     *
     * @return $this
     */
    public function field($name, $options = [])
    {
        // save field in args
        $this->_set_args('field', $name, $options);

        return $this;
    }

    /**
     * Add ID field in template.
     *
     * @param string $name
     *
     * @return $this
     */
    public function id($name = null)
    {
        if (!$name) {
            $name = 'id';
        }

        $val = $this->connection->expr($this->primary_key_expr);

        $this->args['field'] =
            [$name => $val] + (isset($this->args['field']) ? $this->args['field'] : []);

        return $this;
    }

    /**
     * Render "field" template.
     *
     * @return string
     */
    public function _render_field()
    {
        $ret = [];

        if (!$this->args['field']) {
            throw new Exception([
                'No fields defined for table',
            ]);
        }

        foreach ($this->args['field'] as $field => $options) {
            if ($options instanceof Expression) {
                $ret[] = $this->_escape($field).' '.$this->_consume($options);
                continue;
            }

            $ret[] = $this->_render_one_field($field, $options);
        }

        return implode(',', $ret);
    }

    /**
     * Renders one field.
     *
     * @param string $field
     * @param array  $options
     *
     * @return string
     */
    protected function _render_one_field($field, $options)
    {
        $name = $options['name'] ?? $field;
        $type = $this->getSQLFieldType($options['type'] ?? null, $options);

        return $this->_escape($name).' '.$type;
    }

    /**
     * Return fields.
     *
     * @return array
     */
    public function _getFields()
    {
        return $this->args['field'];
    }

    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param string $what  Where to set it - table|field
     * @param string $alias Alias name
     * @param mixed  $value Value to set in args array
     */
    protected function _set_args($what, $alias, $value)
    {
        // save value in args
        if ($alias === null) {
            $this->args[$what][] = $value;
        } else {

            // don't allow multiple values with same alias
            if (isset($this->args[$what][$alias])) {
                throw new Exception([
                    ucfirst($what).' alias should be unique',
                    'alias' => $alias,
                ]);
            }

            $this->args[$what][$alias] = $value;
        }
    }
}
