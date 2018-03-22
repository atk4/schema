<?php

namespace atk4\schema;

use atk4\core\Exception;
use atk4\dsql\Expression;
use atk4\dsql\Expression_MySQL;

class Migration extends Expression_MySQL
{
    /** @var string Expression mode. See $templates. */
    public $mode = 'create';

    /** @var array Expression templates */
    protected $templates = [
        'create' => 'create table {table} ([field])',
        'drop'   => 'drop table if exists {table}',
        'alter'  => 'alter table {table} [statements]',
    ];

    /** @var \atk4\dsql\Connection Database connection */
    public $connection;

    /** @var string Expression to create primary key */
    public $primary_key_expr = 'integer primary key autoincrement';

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
        } elseif ($source instanceof \atk4\data\Persistence_SQL) {
            $this->connection = $source->connection;
            return;
        } elseif ($source instanceof \atk4\data\Model) {
            if ($source->persistence && $source->persistence instanceof \atk4\data\Persistence_SQL) {
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

        foreach($m->elements as $field) {
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

            $this->field($field->actual ?: $field->short_name);  // todo add options here
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
     * Will read current schema and consult current 'field' arguments, to see if they are matched.
     * If table does not exist, will invoke ->create. If table does exist, then it will execute
     * methods ->addColumn(), ->dropColumn()  or ->updateColumn() as needed, then call ->alter()
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

        foreach ($new as $field => $options) {
            if ($field == 'id') {
                continue;
            }

            if (isset($old[$field])) {
                // todo - compare options and if needed, call
                //$this->alterField($field, $options);
                unset($old[$field]);
            } else {
                // new field, so
                $this->newField($field, $options);
                $added++;
                $changes++;
            }
        }

        // remaining fields
        foreach ($old as $field => $options) {
            if ($field == 'id') {
                continue;
            }
            //$this->dropField($field);
        }

        if($changes) {
            $this->alter();
            return 'added '.$added.' field'.($added%10==1?'':'s').' and changed '.$altered;
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
            foreach($this->args['dropField'] as $field => $junk) {
                $result[] = 'drop column '. $this->_escape($field);
            }
        }

        if (isset($this->args['newField'])) {
            foreach($this->args['newField'] as $field => $option) {
                $result[] = 'add column '. $this->_render_one_field($field, $option);
            }
        }

        if (isset($this->args['alterField'])) {
            foreach($this->args['alterField'] as $field => $option) {
                $result[] = 'change column '. $this->_escape($field). ' '. $this->_render_one_field($field, $option);
            }
        }

        return join(', ', $result);
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

            if($field=='id')continue;

            if(is_object($options)) {
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
     * Note: can not rename fields
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
    public function describeTable($table) {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
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
        foreach($this->describeTable($table) as $row) {
            $has_fields = true;
            if ($row['pk']) {
                $this->id($row['name']);
                continue;
            }

            $type = $row['type'];
            if (substr($type, 0,7) == 'varchar') {
                $type = null;
            }

            if (substr($type, 0,4) == 'char') {
                $type = null;
            }
            if (substr($type, 0,4) == 'enum') {
                $type = null;
            }

            if ($type == 'int') {
                $type = 'integer';
            }

            if ($type == 'decimal') {
                $type = 'integer';
            }

            if ($type == 'tinyint') {
                $type = 'boolean';
            }

            if ($type == 'longtext') {
                $type = 'text';
            }

            if ($type == 'longblob') {
                $type = 'text';
            }

            $this->field($row['name'], ['type'=>$type]);
        }

        return $has_fields;
    }

    /**
     * Sets table.
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

        $val = $this->expr($this->primary_key_expr);

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
            $type = strtolower(isset($options['type']) ?
                $options['type'] : 'varchar');
            $type = preg_replace('/[^a-z0-9]+/', '', $type);

            $len = isset($options['len']) ?
                $options['len'] :
                ($type === 'varchar' ? 255 : null);

            return $this->_escape($field).' '.$type.
                ($len ? ('('.$len.')') : '');
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
