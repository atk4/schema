# Agile Data - Schema Add-on

This extension for Agile Data implements ability to work with SQL schema,
execute migrations, perform DB-tests on specific structures

## Example

schema\Migration is a simple class for building schema-related
queries using DSQL.

``` php
<?php
$m = new \atk4\data\schema\Migration($connection);
$m->table('user')->drop();
$m->field('id');
$m->field('name', ['type'=>'string']);
$m->field('age', ['type'=>'integer']);
$m->field('bio');
$m->create();
```

schema\Snapshot is a simple class that can record and restore
table contents:

``` php
<?php
$s = new \atk4\data\schema\Snapshot($connection);
$tables = $s->getDB($tables);

// do anything with tables

$s->setDB($tables);
```

schema\AutoCreator is a simple class reads model and decides
if any changes to the database are needed. Will create a
necessary schema\Migration which you can execute.


## Installation

Add the following inside your `composer.json` file:

``` console
composer require atk4/schema
```

## Current Status

Early development

