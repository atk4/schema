<?php

namespace atk4\schema;

use atk4\core\Exception;
use atk4\data\Field_SQL_Expression;
use atk4\Data\Model;
use atk4\data\Persistence;
use atk4\data\Reference\HasOne;
use atk4\dsql\Connection;
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

    /** @var Connection Database connection */
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
        'datetime'  => ['datetime'],
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
        'date'      => ['date'],
        'datetime'  => ['datetime'],
        'timestamp' => ['datetime'],
        'text'      => ['text'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [];

    /**
     * Factory method to get correct Migration subclass object depending on connection given.
     *
     * @param Connection|Persistence|Model $source
     * @param array                        $params
     *
     * @throws Exception
     *
     * @return Migration Subclass
     */
    public static function getMigration($source, $params = []) : self
    {
        $c = static::getConnection($source);

        switch ($c->driver) {
            case 'sqlite':
                return new Migration\SQLite($source, $params);
            case 'mysql':
                return new Migration\MySQL($source, $params);
            case 'pgsql':
                return new Migration\PgSQL($source, $params);
            case 'oci':
                return new Migration\Oracle($source, $params);
            default:
                throw new Exception([
                    'Not sure which migration class to use for your DSN',
                    'driver' => $c->driver,
                    'source' => $source,
                ]);
        }
    }

    /**
     * Static method to extract DB driver from Connection, Persistence or Model.
     *
     * @param Connection|Persistence|Model $source
     *
     * @throws Exception
     *
     * @return Connection
     */
    public static function getConnection($source) : Connection
    {
        if ($source instanceof Connection) {
            return $source;
        } elseif ($source instanceof Persistence\SQL) {
            return $source->connection;
        } elseif (
            $source instanceof Model
            && $source->persistence
            && ($source->persistence instanceof Persistence\SQL)
        ) {
            return $source->persistence->connection;
        }

        throw new Exception([
            'Source is specified incorrectly. Must be Connection, Persistence or initialized Model',
            'source' => $source,
        ]);
    }

    /**
     * Create new migration.
     *
     * @param Connection|Persistence|Model $source
     * @param array                        $params
     *
     * @throws Exception
     * @throws \atk4\dsql\Exception
     */
    public function __construct($source, $params = [])
    {
        parent::__construct($params);

        $this->setSource($source);
    }

    /**
     * Sets source of migration.
     *
     * @param Connection|Persistence|Model $source
     *
     * @throws Exception
     */
    public function setSource($source)
    {
        $this->connection = static::getConnection($source);

        if (
            $source instanceof Model
            && $source->persistence
            && ($source->persistence instanceof Persistence\SQL)
        ) {
            $this->setModel($source);
        }
    }

    /**
     * Sets model.
     *
     * @param Model $m
     *
     * @throws Exception
     * @throws \ReflectionException
     *
     * @return Model
     */
    public function setModel(Model $m) :Model
    {
        $this->table($m->table);

        foreach ($m->getFields() as $field) {
            // ignore not persisted model fields
            if ($field->never_persist) {
                continue;
            }

            if ($field instanceof Field_SQL_Expression) {
                continue;
            }

            if ($field->short_name == $m->id_field) {
                $this->id($field->actual ?: $field->short_name);
                continue;
            }

            // get field type from field
            $type = $field->type;

            // if the field is a hasOne relation
            // Don't have the right FieldType
            // FieldType is stored in the reference field
            if ($field->reference instanceof HasOne) {

                // @TODO if this can be done better?

                // i don't want to :
                // - change the isolation of relation link
                // - expose the protected property ->their_field
                // i need the type of the field to be used in this table
                $reflection = new \ReflectionClass($field->reference);
                $property = $reflection->getProperty('their_field');
                $property->setAccessible(true);

                // get their_field id
                $reference_their_field = $property->getValue($field->reference);
                $their_field = $reference_their_field ?? $field->reference->owner->id_field;

                $refModelClass = $field->reference->model;

                /** @var Model $refModel */
                $refModel = new $refModelClass($m->persistence);
                $refModel->getField($their_field);

                $type = $refModel->getField($their_field)->type ?? 'integer';
            }

            $this->field($field->actual ?: $field->short_name, ['type' => $type]);  // todo add more options here
        }

        return $m;
    }

    /**
     * Set SQL expression template.
     *
     * @param string $mode Template name
     *
     * @throws Exception
     *
     * @return $this
     */
    public function mode(string $mode) :self
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
     * @throws Exception
     * @throws \atk4\dsql\Exception
     *
     * @return $this
     */
    public function create() :self
    {
        $this->mode('create')->execute();

        return $this;
    }

    /**
     * Drop table.
     *
     * @throws Exception
     * @throws \atk4\dsql\Exception
     *
     * @return $this
     */
    public function drop() :self
    {
        $this->mode('drop')->execute();

        return $this;
    }

    /**
     * Alter table.
     *
     * @throws Exception
     * @throws \atk4\dsql\Exception
     *
     * @return $this
     */
    public function alter() :self
    {
        $this->mode('alter')->execute();

        return $this;
    }

    /**
     * Rename table.
     *
     * @throws Exception
     * @throws \atk4\dsql\Exception
     *
     * @return $this
     */
    public function rename() :self
    {
        $this->mode('rename')->execute();

        return $this;
    }

    /**
     * Will read current schema and consult current 'field' arguments, to see if they are matched.
     * If table does not exist, will invoke ->create. If table does exist, then it will execute
     * methods ->newField(), ->dropField() or ->alterField() as needed, then call ->alter().
     *
     * @throws Exception
     *
     * @return string Returns short textual info for logging purposes
     */
    public function migrate() :string
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
    public function _render_statements() :string
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
     * @param Persistence $persistence
     * @param string      $table
     *
     * @throws Exception
     * @throws \atk4\data\Exception
     *
     * @return Model
     */
    public function createModel($persistence, $table = null) : Model
    {
        $this['table'] = $table ?? $this['table'];

        $m = new Model([$persistence, 'table'=> $this['table']]);

        $this->importTable($this['table']);

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
     * @throws Exception
     *
     * @return $this
     */
    public function newField($field, $options = []) :self
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
     * @throws Exception
     *
     * @return $this
     */
    public function alterField(string $field, $options = []) :self
    {
        $this->_set_args('alterField', $field, $options);

        return $this;
    }

    /**
     * Sets dropField argument.
     *
     * @param string $field
     *
     * @throws Exception
     *
     * @return $this
     */
    public function dropField($field) :self
    {
        $this->_set_args('dropField', $field, true);

        return $this;
    }

    /**
     * Return database table descriptions.
     * DB engine specific.
     *
     * @todo Maybe convert to abstract function
     *
     * @param string $table
     *
     * @return array
     */
    public function describeTable(string $table) : array
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
    public function getModelFieldType(string $type) :?string
    {
        // remove parenthesis
        $type = trim(preg_replace('/\(.*/', '', strtolower($type)));

        $map = array_replace($this->defaultMapToAgile, $this->mapToAgile);
        $a = array_key_exists($type, $map) ? $map[$type] : $map[0];

        return $a[0];
    }

    /**
     * Convert Agile Data field types to SQL field types.
     *
     * @param string $type    Agile Data field type
     * @param array  $options More options
     *
     * @return string|null
     */
    public function getSQLFieldType(?string $type, ?array $options = null) :?string
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
     * @throws Exception
     *
     * @return bool
     */
    public function importTable(string $table) :bool
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
     * @throws Exception
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
     * @throws Exception
     * @throws \atk4\dsql\Exception
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
    protected function _render_one_field(string $field, array $options) :string
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
    public function _getFields() :array
    {
        return $this->args['field'];
    }

    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param string $what  Where to set it - table|field
     * @param string $alias Alias name
     * @param mixed  $value Value to set in args array
     *
     * @throws Exception
     */
    protected function _set_args(string $what, string $alias, $value)
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
