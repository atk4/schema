<?php
include '../vendor/autoload.php';
$db = \atk4\data\Persistence::connect('mysql://root:root@localhost/test');

$db->connection = new \atk4\dsql\Connection_Dumper(['connection'=>$db->connection]);
