<?php

namespace atk4\schema\tests;

use atk4\data\Model;
use atk4\schema\PHPUnit_SchemaTestCase;

class MigrationTest extends PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();
        // drop table
        $this->dropTable('user');

        // create table
        $user = new TestCreationUser($this->db);
        $migration = $this->getMigration($user);
        $migration->create();
    }

    public function testCreateModelFromTable()
    {
        $Migration = $this->getMigration();
        $excepted = '<?php

namespace \Your\Project\Models;

class User extends \atk4\data\Model
{
    /** @var string $table table of the model */
    public $table = "user";
    
    
    public function init()
    {
        parent::init();
        
        $this->addField("name",["type"=>"string"]);
        $this->addField("password",["type"=>"string"]);
        $this->addField("is_admin",["type"=>"boolean"]);
        $this->addField("notes",["type"=>"text"]);
        $this->addField("test_has_one_id",["type"=>"integer"]);
        $this->addField("test_has_one_string",["type"=>"string"]);

    }
}';

        $output = $Migration->getModelPHPCode('user', 'User', 'id', '\Your\Project\Models');

        $this->assertEquals($excepted, $output);
    }
}


class TestCreationUser extends Model
{

    public $table = 'user';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', ['type' => 'password']);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->addExpression('test_expression',['concat'=> ['name','notes']]);

        // test has one with type integer
        $this->hasOne('test_has_one_id', TestCreationUserReferenceOne::class);

        // test has one with type string
        $this->hasOne('test_has_one_string', [
            TestCreationUserReferenceOne::class,
            'their_field' => 'name'
        ]);

        // test has many
        $this->hasMany('test_has_many', TestCreationUserReferenceOne::class);
    }
}

class TestCreationUserReferenceOne extends Model
{

    public $table = 'user_one';

    public function init()
    {
        parent::init();

        $this->addField('name',['type' => 'string']);
    }
}
