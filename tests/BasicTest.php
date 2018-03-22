<?php

namespace atk4\ui\tests;

use \atk4\schema\Migration\SQLite as Migration;

class BasicTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    /**
     * Test constructor.
     */
    public function testCreateAndAlter()
    {
        $this->dropTable('user');
        $m = $this->getMigration();
        $m->table('user')->id()
            ->field('foo')
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
            ->create();

        $m = $this->getMigration();
        $m->table('user')
            ->newField('zed', ['type'=>'integer'])
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
        $m = $this->getMigration();
        $m->table('user')->id()
            ->field('foo')
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
            ->create();

        $m = $this->getMigration();
        $m->table('user')
            ->dropField('bar', ['type'=>'integer'])
            ->alter();
    }
}
