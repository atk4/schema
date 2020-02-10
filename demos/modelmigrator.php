<?php

include 'init.php';

class User extends \atk4\data\Model
{
    public function init()
    {
        parent::init();

        $this->addField('name');
    }
}

$m = new User($db, 'user');

try {
    // apply migrator
    (\atk4\schema\Migration::getMigration($m))->migrate();

    // ok, now we surely have DB!

    $m->save([
        'name' => 'John' . rand(1, 100),
    ]);
} catch (\atk4\core\Exception $e) {
    echo $e->getColorfulText();
}
