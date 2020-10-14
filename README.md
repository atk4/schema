# !!! repo was integrated into atk4/data !!!

# [https://github.com/atk4/data](https://github.com/atk4/data)

<br><br><br><br>

# Agile Data - SQL Schema Management Add-on

This extension for Agile Data implements ability to work with SQL schema, execute migrations, perform DB-tests in PHPUnit (used by other ATK frameworks) and sync up "Model" structure to the database.

![Build](https://github.com/atk4/schema/workflows/Unit%20Testing/badge.svg)
[![CodeCov](https://codecov.io/gh/atk4/schema/branch/develop/graph/badge.svg)](https://codecov.io/gh/atk4/schema)
[![GitHub release](https://img.shields.io/github/release/atk4/schema.svg)](CHANGELOG.md)
[![Code Climate](https://codeclimate.com/github/atk4/schema/badges/gpa.svg)](https://codeclimate.com/github/atk4/schema)


### Basic Usage:

``` php
// Add the following code on your setup page / wizard:

$app->add('MigratorConsole')
    ->migrateModels([
        new Model\User($app->db), 
        new Model\Order($app->db),
        new Model\Payment($app->db)
    ]);
```

The user will see a console which would adjust database to contain required tables / fields for the models:

![migrator-console](docs/migrator-console.png)

Of course it's also possible to perform migration without visual feedback:

``` php
$changes = \atk4\schema\Migration::of(new User($app->db))->run();
```

If you need a more fine-graned migration, you can define them in great detail.

``` php
// create table
$migrator = \atk4\schema\Migration::of($app->db);
$migrator->table('user')
    ->id()
    ->field('name')
    ->field('address', ['type'=>'text']);
    ->create();

// or alter
$migrator = \atk4\schema\Migration::of($app->db);
$migrator->table('user')
    ->newField('age', ['type'=>'integer'])
    ->alter();
```

Currently atk4/schema fully supports MySQL and SQLite databases and partly PostgreSQL and Oracle.
Other SQL databases are not yet natively supported but you can register your migrator class at runtime.

``` php

// $dbDriver is the connection driver name
// MyCustomMigrator::class should be extending \atk4\schema\Migration

\atk4\schema\Migration::register($platformClass, MyCustomMigrator::class);

```

Field declaration uses same types as [ATK Data](https://github.com/atk4/data).

## Examples

`schema\Migration` is a simple class for building schema-related
queries using DSQL.

``` php
<?php
$migrator = \atk4\data\schema\Migration::of($connection);
$migrator->table('user')->drop();
$migrator->field('id');
$migrator->field('name', ['type'=>'string']);
$migrator->field('age', ['type'=>'integer']);
$migrator->field('bio');
$migrator->create();
```

`schema\Snapshot` (NOT IMPLEMENTED) is a simple class that can record and restore
table contents:

``` php
<?php
$s = new \atk4\data\schema\Snapshot($connection);
$tables = $s->getDb($tables);

// do anything with tables

$s->setDb($tables);
```

## Integration with PHPUnit

You can now automate your database testing by setting and checking your
database contents easier. First, extend your test-script from
`\atk4\schema\PhpunitTestCase`. 

Next, you need to set your schema

``` php
$q = ['user' => [
    ['name' => 'John', 'surname' => 'Smith'],
    ['name' => 'Steve', 'surname' => 'Jobs'],
]];
$this->setDb($q);
```

Perform any changes, then execute:

```
$this->assertEquals($q, $this->getDb('user'));
```

To ensure that database remained the same. Of course you can compare
against any other state. 

- Automatically add 'id' field by default
- Create tables for you
- Detect types (int, string, date, boolean etc)
- Hides ID values if you don't pass them

## Installation

Add the following inside your `composer.json` file:

``` console
composer require atk4/schema
```
