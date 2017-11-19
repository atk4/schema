<?php

namespace atk4\schema\tests;

use \atk4\schema\Migration;

class ModelTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testSetModelCreate()
    {
        $user = new Testuser($this->db);

        $migration = new Migration($user);
        $migration->create();

        // now we can use user
        $user->save(['name'=>'john', 'is_admin'=>true, 'notes'=>'some long notes']);
    }

    public function testImportTable()
    {
        //$user = new Testuser($this->db);
        $m = new Migration($this->db);
        $m->table('user')->id()->field('foo')->field('bar', ['type'=>'integer'])->field('baz', ['type'=>'text'])->create();
        $this->db->dsql()->table('user')->set('id', 1)->set('foo', 'foovalue')->set('bar', 123)->set('baz','long text value')->insert();

        $m2 = new Migration($this->db);
        $m2->importTable('user');

        $m2->mode('create');
        $this->assertEquals($m->getDebugQuery(), $m2->getDebugQuery());
    }

    public function testMigrateTable()
    {
        //$user = new Testuser($this->db);
        $m = new Migration($this->db);
        $m->table('user')->id()->field('foo')->field('bar', ['type'=>'integer'])->field('baz', ['type'=>'text'])->create();
        $this->db->dsql()->table('user')->set('id', 1)->set('foo', 'foovalue')->set('bar', 123)->set('baz','long text value')->insert();

        $m2 = new Migration($this->db);
        $m2->table('user')->id()->field('xx')->field('bar', ['type'=>'integer'])->field('baz')->migrate();
        // field foo should be gone, xx should be added, bar should remain unchanged and baz should change from text to varchar
    }
}

class TestUser extends \atk4\data\Model {
    public $table = 'user';

    function init() {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type'=>'password']);
        $this->addField('is_admin', ['type'=>'boolean']);
        $this->addField('notes', ['type'=>'text']);
    }

}
