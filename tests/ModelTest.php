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
            ->field('bar', ['type'=>'integer'])
            ->field('baz', ['type'=>'text'])
	
	        ->field('bl', ['type'=>'boolean'])
	        
	        ->field('tm', ['type'=>'time'])
	        ->field('dt', ['type'=>'date'])
	        ->field('dttm', ['type'=>'datetime'])
	
	        ->field('dbl', ['type'=>'double'])
	        ->field('fl', ['type'=>'float'])
	        ->field('mn', ['type'=>'money'])
	
	        ->field('en', ['type'=>'enum'])
	
	        ->create();
        
        $this->db->dsql()->table('user')
            ->set([
                'id'  => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
                
                'bl'  => true,
                
                'tm'  => '11:11:11',
                'dt'  => '2019-02-04',
                'dttm'  => '2019-02-04 11:11:11',

                'dbl'  => 12345678901.2345678,
                'fl'  => 10.56,
                'mn'  => 99.99,

                'en'  => 'enumA',
                
            ])->insert();

        $m2 = $this->getMigration();
        $m2->importTable('user');

        $m2->mode('create');
        $this->assertEquals($m->getDebugQuery(), $m2->getDebugQuery());
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
	
	        ->field('bl', ['type'=>'boolean'])
	
	        ->field('tm', ['type'=>'time'])
	        ->field('dt', ['type'=>'date'])
	        ->field('dttm', ['type'=>'datetime'])
	
	        ->field('dbl', ['type'=>'double'])
	        ->field('fl', ['type'=>'float'])
	        ->field('mn', ['type'=>'money'])
	
	        ->field('en', ['type'=>'enum'])
	
	        ->create();
        $this->db->dsql()->table('user')
            ->set([
                'id'  => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',

                'bl'  => true,

                'tm'  => '11:11:11',
                'dt'  => '2019-02-04',
                'dttm'  => '2019-02-04 11:11:11',

                'dbl'  => 12345678901.2345678,
                'fl'  => 10.56,
                'mn'  => 99.99,

                'en'  => 'enumA',

            ])->insert();

        $m2 = $this->getMigration($this->db);
        $m2->table('user')->id()
            ->field('xx')
            ->field('bar', ['type'=>'integer'])
            ->field('baz')
            ->migrate();
    }
	
	public function testByFieldType()
	{
		$Transcode
			= [
			'integer'  => 500,
			'string'   => 'string',
			'password' => '123456',
			'double'   => 123456789012.345678,
			'float'    => 10.11,
			'money'    => 99.99,
			'date'     => '2019-02-04',
			'datetime' => '2019-02-04 11:11:11',
			'time'     => '11:11:11',
			'text'     => str_repeat('this is a long text ',50),
			'array'    => [0,1,2,3,[11,22,33]],
			'object'   => (object) ['test'=>'test'],
			'boolean'  => true,
		];
		
		foreach($Transcode as $fieldType => $fieldValue)
		{
			$this->dropTable('user');
			
			$m = $this->getMigration();
			$m->table('user')->id()
			  ->field('__'.$fieldType, ['type' => $fieldType])
			  ->create()
			;
			
			$value = $fieldValue;
			if(is_object($value) || is_array($value))
			{
				$value = serialize($value);
			}
			
			$this->db->dsql()->table('user')
			         ->set([
				         'id'         => 1,
				         '__'.$fieldType => $value
			         ])->insert()
			;
			
			$m2 = $this->getMigration();
			$m2->importTable('user');
			
			$m2->mode('create');
			$this->assertEquals($m->getDebugQuery(), $m2->getDebugQuery());
		}
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
        $this->hasOne('test', TestUserReferenceOne::class);
    }
}


class TestUserReferenceOne extends TestUser
{
    public $table = 'user_one';
    
    public function init()
    {
        parent::init();
        
        $this->addField('name');
    }
}
