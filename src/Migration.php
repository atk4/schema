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
    ];

    /** @var \atk4\dsql\Connection Database connection */
    public $connection;

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
            'source'=>$source
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
     * methods ->addField(), ->dropField()  or ->updateField() as needed, then call ->alter()
     */
    public function migrate()
    {

        // We use this to read fields from SQL
        $migration2 = new self($this->connection);

        try {
            $migration2->importTable($this['table']);
        } catch (Exception $e) {
            // should probably use custom exception class here
            return $this->create();
        }

        $old = $migration2->_getFields();
        $new = $this->_getFields();

        foreach ($new as $field => $options) {
            if ($field == 'id')continue;


            if (isset($old[$field])) {
                // todo - compare options and if needed, call
                $this->alterField($field, $options);
                unset($old[$field]);
            } else {
                // new field, so
                $this->newField($field, $options);
            }
        }

        // remaining fields
        foreach ($old as $field => $options) {
            if ($field == 'id')continue;
            $this->dropField($field);
        }

        return $this->alter();
    }

    public function _render_statements()
    {
        $result = [];

        if (isset($this->args['dropField'])) foreach($this->args['dropField'] as $field => $junk) {
            $result[] = 'drop '. $this->_escape($field);
        }

        if (isset($this->args['addField'])) foreach($this->args['addField'] as $field => $option) {
            $result[] = 'add '. $this->_render_one_field($field, $option);
        }

        if (isset($this->args['alterField'])) foreach($this->args['alterField'] as $field => $option) {
            $result[] = 'update '. $this->_escape($field). ' '. $this->_render_one_field($field, $option);
        }

        return join(', ', $result);
    }


    /**
     * Create rough model from current set of $this->args['fields']. This is not
     * ideal solution but is designed as a drop-in solution.
     */
    public function createModel(\atk4\data\Persintence $persistence, $table = null)
    {
        $m = new \atk4\data\Model($persistence);
        if ($table) {
            $m->table = $table;
        }

        foreach ($this->args['field'] as $field => $options) {

            $defaults = [];

            if ($options['type']) {
                $defaults['type'] = $options['type'];
            }
            $m->addField($field, $defaults);
        }

        return $m;
    }

    public function newField($field, $options = [])
    {
        $this->_set_args('newField', $field, $options);
    }

    /**
     * cannot rename fields
     */
    public function alterField($field, $options = [])
    {
        $this->_set_args('alterField', $field, $options);
    }

    public function dropField($field)
    {
        $this->_set_args('dropField', $field, true);
    }

    public function importTable($table)
    {
        $this->table($table);
        foreach($this->connection->expr('pragma table_info({})', [$table]) as $row) {
            if ($row['pk']) {
                $this->id($row['name']);
                continue;
            }

            $type = $row['type'];
            if (substr($type, 0,7) == 'varchar') {
                $type = null;
            }

            $this->field($row['name'], ['type'=>$type]);
        }
    }

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

        $val = $this->expr('integer primary key autoincrement');

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
