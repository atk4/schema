<?php

namespace atk4\schema;

use atk4\data\Persistence;

class PHPUnit_SchemaTestCase extends \atk4\core\PHPUnit_AgileTestCase
{
    /** @var \atk4\data\Persistence Persistence instance */
    public $db;

    /** @var array Array of database table names */
    public $tables = null;

    /** @var bool Debug mode enabled/disabled. In debug mode will use Dumper persistence */
    public $debug = false;

    /** @var string What DB driver we use - mysql, sqlite, pgsql etc */
    public $driver = 'sqlite';

    /**
     * Setup test database.
     */
    public function setUp()
    {
        parent::setUp();

        // establish connection
        $dsn = getenv('DSN');
        if ($dsn) {
            $this->db = Persistence::connect(($this->debug ? ('dumper:') : '').$dsn);
            list($this->driver, $junk) = explode(':', $dsn, 2);
            $this->driver = strtolower($this->driver);
        } else {
            $this->db = Persistence::connect(($this->debug ? ('dumper:') : '').'sqlite::memory:');
        }
    }

    /**
     * Create and return appropriate Migration object.
     *
     * @param \atk4\dsql\Connection|\atk4\data\Persistence|\atk4\data\Model $m
     *
     * @return Migration
     */
    public function getMigration($m = null)
    {
        switch ($this->driver) {
            case 'sqlite':
                return new \atk4\schema\Migration\SQLite($m ?: $this->db);
            case 'mysql':
                return new \atk4\schema\Migration\MySQL($m ?: $this->db);
            //case 'pgsql':
            //    return new \atk4\schema\Migration\PgSQL($m ?: $this->db);
            //case 'oci':
            //    return new \atk4\schema\Migration\Oracle($m ?: $this->db);
            default:
                throw new \atk4\core\Exception([
                    'Not sure which migration class to use for your DSN',
                    'driver' => $this->driver,
                    'dsn' => getenv('DSN'),
                ]);
        }
    }

    /**
     * Use this method to clean up tables after you have created them,
     * so that your database would be ready for the next test.
     *
     * @param string $table Table name
     */
    public function dropTable($table)
    {
        $this->db->connection->expr("drop table if exists {}", [$table])->execute();
    }

    /**
     * Sets database into a specific test.
     *
     * @param array $db_data
     * @param bool  $import_data Should we import data of just create table
     */
    public function setDB($db_data, $import_data = true)
    {
        $this->tables = array_keys($db_data);

        // create tables
        foreach ($db_data as $table => $data) {
            $s = $this->getMigration();

            // drop table
            $s->table($table)->drop();

            // create table and fields from first row of data
            $first_row = current($data);
            if ($first_row) {
                foreach ($first_row as $field => $row) {
                    if ($field === 'id') {
                        $s->id('id');
                        continue;
                    }

                    if (is_int($row)) {
                        $s->field($field, ['type' => 'integer']);
                        continue;
                    } elseif (is_float($row)) {
                        $s->field($field, ['type' => 'numeric(10,5)']);
                        continue;
                    } elseif ($row instanceof \DateTime) {
                        $s->field($field, ['type' => 'datetime']);
                        continue;
                    }

                    $s->field($field);
                }
            }

            if (!isset($first_row['id'])) {
                $s->id();
            }

            $s->create();

            // import data
            if ($import_data) {
                $has_id = (bool) key($data);

                foreach ($data as $id => $row) {
                    $s = $this->db->dsql();
                    if ($id === '_') {
                        continue;
                    }

                    $s->table($table);
                    $s->set($row);

                    if (!isset($row['id']) && $has_id) {
                        $s->set('id', $id);
                    }

                    $s->insert();
                }
            }
        }
    }

    /**
     * Return database data.
     *
     * @param array $tables Array of tables
     * @param bool  $no_id
     *
     * @return array
     */
    public function getDB($tables = null, $no_id = false)
    {
        if (!$tables) {
            $tables = $this->tables;
        }

        if (is_string($tables)) {
            $tables = array_map('trim', explode(',', $tables));
        }

        $ret = [];

        foreach ($tables as $table) {
            $data2 = [];

            $s = $this->db->dsql();
            $data = $s->table($table)->get();

            foreach ($data as &$row) {
                foreach ($row as &$val) {
                    if (is_int($val)) {
                        $val = (int) $val;
                    }
                }

                if ($no_id) {
                    unset($row['id']);
                    $data2[] = $row;
                } else {
                    $data2[$row['id']] = $row;
                }
            }

            $ret[$table] = $data2;
        }

        return $ret;
    }
}
