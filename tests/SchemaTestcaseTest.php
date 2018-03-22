<?php

namespace atk4\schema\tests;

class SchemaTestcaseTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testInit()
    {
        $this->setDB($q = ['user' => [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Steve', 'surname' => 'Jobs'],
        ]]);

        $q2 = $this->getDB('user');

        $this->setDB($q2);
        $q3 = $this->getDB('user');

        $this->assertEquals($q2, $q3);

        $this->assertEquals($q, $this->getDB('user', true));
    }
}
