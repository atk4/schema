<?php

declare(strict_types=1);

include '../vendor/autoload.php';
$db = \atk4\data\Persistence::connect('mysql://root:root@localhost/test');

$db->connection = new \atk4\dsql\Debug\Stopwatch\Connection(['connection' => $db->connection]);
