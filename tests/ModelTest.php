<?php

namespace atk4\schema\tests;

class ModelTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testSetModelCreate()
    {
        $this->dropTable('user');
        $user = new TestUser($this->db);

        $migration = $this->getMigration($user);
        $migration->create();

        // now we can use user
        $user->save(['name'=>'john', 'is_admin'=>true, 'notes'=>'some long notes']);
    }

    public function testImportTable()
    {
        $this->dropTable('user');

        $m = $this->getMigration();
        $m->table('user')->id()
            ->field('foo')
            ->field('str', ['type'=>'string'])
            ->field('bool', ['type'=>'boolean'])
            ->field('int', ['type'=>'integer'])
            ->field('mon', ['type'=>'money'])
            ->field('flt', ['type'=>'float'])
            ->field('date', ['type'=>'date'])
            ->field('datetime', ['type'=>'datetime'])
            ->field('time', ['type'=>'time'])
            ->field('txt', ['type'=>'text'])
            ->field('arr', ['type'=>'array'])
            ->field('obj', ['type'=>'object'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id'  => 1,
                'foo' => 'quite short value, max 255 characters',
                'str' => 'quite short value, max 255 characters',
                'bool' => true,
                'int' => 123,
                'mon' => 123.45,
                'flt' => 123.456789,
                'date' => (new \DateTime())->format('Y-m-d'),
                'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                'time' => (new \DateTime())->format('H:i:s'),
                'txt' => 'very long text value'.str_repeat("-=#", 1000), // 3000+ chars
                'arr' => 'very long text value'.str_repeat("-=#", 1000), // 3000+ chars
                'obj' => 'very long text value'.str_repeat("-=#", 1000), // 3000+ chars
            ])->insert();

        $m2 = $this->getMigration();
        $m2->importTable('user');

        $m2->mode('create');

        $q1 = preg_replace('/\([0-9,]*\)/i', '', $m->getDebugQuery()); // remove parentesis otherwise we can't differe money from float etc.
        $q2 = preg_replace('/\([0-9,]*\)/i', '', $m2->getDebugQuery());
        $this->assertEquals($q1, $q2);
    }

    public function testMigrateTable()
    {
        if ($this->driver == 'sqlite') {
            // SQLite doesn't support DROP COLUMN in ALTER TABLE
            // http://www.sqlitetutorial.net/sqlite-alter-table/
            $this->markTestIncomplete('This test is not supported on '.$this->driver);
        }

        $this->dropTable('user');
        $m = $this->getMigration($this->db);
        $m->table('user')->id()
            ->field('foo')
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id'  => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->insert();

        $m2 = $this->getMigration($this->db);
        $m2->table('user')->id()
            ->field('xx')
            ->field('bar', ['type'=>'integer'])
            ->field('baz')
            ->migrate();
    }
}

class TestUser extends \atk4\data\Model
{
    public $table = 'user';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type'=>'password']);
        $this->addField('is_admin', ['type'=>'boolean']);
        $this->addField('notes', ['type'=>'text']);
    }
}
