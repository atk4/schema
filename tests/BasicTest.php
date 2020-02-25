<?php

namespace atk4\schema\tests;

use atk4\core\Exception;
use atk4\schema\Migration;
use atk4\schema\PHPUnit_SchemaTestCase;

class CustomMySQLMigrator extends Migration
{
}

class CustomMigrator
{
}

class BasicTest extends PHPUnit_SchemaTestCase
{
    /**
     * Test constructor.
     */
    public function testCreateAndAlter()
    {
        $this->dropTable('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('dbl', ['type' => 'double'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
            ->field('en', ['type' => 'enum'])
            ->create();

        $this->getMigrator()->table('user')
            ->newField('zed', ['type' => 'integer'])
            ->alter();
    }

    /**
     * Tests creating and dropping of tables.
     */
    public function testCreateAndDrop()
    {
        if ($this->driver == 'sqlite') {
            $this->markTestSkipped('SQLite does not support drop');
        }

        $this->dropTable('user');

        $this->getMigrator()->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('dbl', ['type' => 'double'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'money'])
            ->field('en', ['type' => 'enum'])
            ->create();

        $this->getMigrator()->table('user')
            ->dropField('bar', ['type' => 'integer'])
            ->alter();
    }

    /**
     * Tests creating direct migrator.
     */
    public function testDirectMigratorResolving()
    {
        $migrator = $this->getMigrator();

        $migratorClass = get_class($migrator);

        $directMigrator = $migratorClass::of($this->db);

        $this->assertEquals($migratorClass, get_class($directMigrator));
    }

    /**
     * Tests registering migrator.
     */
    public function testMigratorRegistering()
    {
        // get original migrator registration
        $origMigratorClass = get_class($this->getMigrator());

        Migration::register($this->driver, CustomMySQLMigrator::class);

        $this->assertEquals(CustomMySQLMigrator::class, get_class($this->getMigrator()));

        CustomMySQLMigrator::register($this->driver);

        $this->assertEquals(CustomMySQLMigrator::class, get_class($this->getMigrator()));

        // restore original migrator registration
        Migration::register($this->driver, $origMigratorClass);

        $this->expectException(Exception::class);

        Migration::register($this->driver, CustomMigrator::class);
    }
}
