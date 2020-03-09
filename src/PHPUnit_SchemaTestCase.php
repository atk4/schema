<?php

namespace atk4\schema;

use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Connection;

// NOTE: This class should stay here in this namespace because other repos rely on it. For example, atk4\data tests
class PHPUnit_SchemaTestCase extends \atk4\core\PHPUnit_AgileTestCase
{
    /** @var \atk4\data\Persistence Persistence instance */
    public $db;

    /** @var array Array of database table names */
    public $tables = null;

    /** @var bool Debug mode enabled/disabled. In debug mode will use Dumper persistence */
    public $debug = false;

    /** @var string DSN string */
    protected $dsn;

    /** @var string What DB driver we use - mysql, sqlite, pgsql etc */
    public $driver = 'sqlite';

    /**
     * Setup test database.
     */
    public function setUp()
    {
        parent::setUp();

        // establish connection
        $this->dsn = ($this->debug ? ('dumper:') : '').($GLOBALS['DB_DSN'] ?? 'sqlite::memory:');
        $user = $GLOBALS['DB_USER'] ?? null;
        $pass = $GLOBALS['DB_PASSWD'] ?? null;

        $this->db = Persistence::connect($this->dsn, $user, $pass);
        $this->driver = $this->db->connection->driver;
    }

    public function tearDown()
    {
        unset($this->db);

        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    /**
     * Create and return appropriate Migration object.
     *
     * @param Connection|Persistence|Model $m
     *
     * @return Migration
     */
    public function getMigrator($model = null)
    {
        return \atk4\schema\Migration::of($model ?: $this->db);
    }

    /**
     * Use this method to clean up tables after you have created them,
     * so that your database would be ready for the next test.
     *
     * @param string $table Table name
     */
    public function dropTable($table)
    {
        $this->getMigrator()->table($table)->drop();
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
            $migrator = $this->getMigrator();

            // drop table
            $migrator->table($table)->drop();

            // create table and fields from first row of data
            $first_row = current($data);
            if ($first_row) {
                foreach ($first_row as $field => $row) {
                    if ($field === 'id') {
                        $migrator->id('id');
                        continue;
                    }

                    if (is_int($row)) {
                        $migrator->field($field, ['type' => 'integer']);
                        continue;
                    } elseif (is_float($row)) {
                        $migrator->field($field, ['type' => 'numeric(10,5)']);
                        continue;
                    } elseif ($row instanceof \DateTime) {
                        $migrator->field($field, ['type' => 'datetime']);
                        continue;
                    }

                    $migrator->field($field);
                }
            }

            if (!isset($first_row['id'])) {
                $migrator->id();
            }

            $migrator->create();

            // import data
            if ($import_data) {
                $has_id = (bool) key($data);

                foreach ($data as $id => $row) {
                    $migrator = $this->db->dsql();
                    if ($id === '_') {
                        continue;
                    }

                    $migrator->table($table);
                    $migrator->set($row);

                    if (!isset($row['id']) && $has_id) {
                        $migrator->set('id', $id);
                    }

                    $migrator->insert();
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
        $tables = $tables ?: $this->tables;

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
    
    /**
     * Return escape character of current DB connection.
     *
     * @return string
     */
    public function getEscapeChar()
    {
        return $this->getProtected($this->db->dsql(), 'escape_char');
    }
}
