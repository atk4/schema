<?php

namespace atk4\schema\tests;

use \atk4\schema\Migration\SQLite as Migration;

class ModelTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testSetModelCreate()
    {
        $this->dropTable('user');
        $user = new Testuser($this->db);

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
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->insert();

        $m2 = $this->getMigration();
        $m2->importTable('user');

        $m2->mode('create');
        $this->assertEquals($m->getDebugQuery(), $m2->getDebugQuery());
    }

    public function testMigrateTable()
    {
        $this->dropTable('user');
        $m = $this->getMigration($this->db);
        $m->table('user')->id()
            ->field('foo')
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
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

class TestUser extends \atk4\data\Model {
    public $table = 'user';

    public function init() {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type'=>'password']);
        $this->addField('is_admin', ['type'=>'boolean']);
        $this->addField('notes', ['type'=>'text']);
    }

}
