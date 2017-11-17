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
    protected $connection;

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
    protected function mode($mode)
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
     * methods ->addField(), ->removeField()  or ->updateField() as needed, then call ->alter()
     */
    public function migrate()
    {

        // We use this to read fields from SQL
        $migration2 = new self($this->connection);

        try {
            $migration2->loadFromTable($this->table);
        } catch (Exception $e) {
            // should probably use custom exception class here
            return $this->create();
        }

        foreach ($this->args['field'] as $field => $options) {
            if (isset($migration2->args['field'][$field])) {
                // field already exist. Lets compare options


                // todo - compare options and if needed, call
                // $this->alterField($field, $options);
                unset($migration2->args['field'][$field]);
            } else {
                // new field, so
                $this->newField($field, $options);
            }
        }

        // remaining fields
        foreach ($migration2->args['field'] as $field => $options) {
            $this->removeField($args['field']);
        }

        return $this->alter();
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
    }

    public function alterField($field, $options = [])
    {
    }

    public function removeField($field, $options = [])
    {
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

            $type = strtolower(isset($options['type']) ?
                $options['type'] : 'varchar');
            $type = preg_replace('/[^a-z0-9]+/', '', $type);

            $len = isset($options['len']) ?
                $options['len'] :
                ($type === 'varchar' ? 255 : null);

            $ret[] = $this->_escape($field).' '.$type.
                ($len ? ('('.$len.')') : '');
        }

        return implode(',', $ret);
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
