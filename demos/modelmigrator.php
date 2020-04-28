<?php

include 'init.php';

class User extends \atk4\data\Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('name');
    }
}

$user = new User($db, 'user');

try {
    // run migrator
    \atk4\schema\Migration::of($user)->run();

    // ok, now we surely have DB!

    $user->save([
        'name' => 'John' . random_int(1, 100),
    ]);
} catch (\atk4\core\Exception $e) {
    echo $e->getColorfulText();
}
