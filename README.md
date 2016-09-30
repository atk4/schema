# Agile Data - Schema Add-on

This extension for Agile Data implements ability to work with SQL schema,
execute migrations, perform DB-tests on specific structures

Code Quality:

[![Build Status](https://travis-ci.org/atk4/schema.png?branch=develop)](https://travis-ci.org/atk4/schema)
[![Code Climate](https://codeclimate.com/github/atk4/schema/badges/gpa.svg)](https://codeclimate.com/github/atk4/schema)
[![StyleCI](https://styleci.io/repos/69662508/shield)](https://styleci.io/repos/69662508)
[![Test Coverage](https://codeclimate.com/github/atk4/schema/badges/coverage.svg)](https://codeclimate.com/github/atk4/schema)

Resources and Community:

[![Documentation Status](https://readthedocs.org/projects/agile-schema/badge/?version=develop)](http://agile-schema.readthedocs.io/en/develop/?badge=latest)
[![Gitter](https://img.shields.io/gitter/room/atk4/atk4.svg?maxAge=2592000)](https://gitter.im/atk4/atk4?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Stack Overlfow Community](https://img.shields.io/stackexchange/stackoverflow/t/atk4.svg?maxAge=2592000)](http://stackoverflow.com/questions/ask?tags=atk4)
[![Discord User forum](https://img.shields.io/badge/discord-User_Forum-green.svg)](https://forum.agiletoolkit.org/c/44)

Stats:

[![Version](https://badge.fury.io/gh/atk4%2Fschema.svg)](https://packagist.org/packages/atk4/schema)


## Example

`schema\Migration` is a simple class for building schema-related
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

`schema\Snapshot` is a simple class that can record and restore
table contents:

``` php
<?php
$s = new \atk4\data\schema\Snapshot($connection);
$tables = $s->getDB($tables);

// do anything with tables

$s->setDB($tables);
```

`schema\AutoCreator` is a simple class reads model and decides
if any changes to the database are needed. Will create a
necessary schema\Migration which you can execute.

``` php
<?php
$a = new \atk4\data\schema\AutoCreator($m_order);

// or

$a = new \atk4\data\schema\AutoCreator($m_order, ['no_auto' => true]);
$a->compare()->execute();
```

## Integration with PHPUnit

You can now automate your database testing by setting and checking your
database contents easier. First, extend your test-script from
`\atk4\schema\PHPUnit_SchemaTestCase`. 

Next, you need to set your schema

``` php
$q = ['user' => [
    ['name' => 'John', 'surname' => 'Smith'],
    ['name' => 'Steve', 'surname' => 'Jobs'],
]];
$this->setDB($q);
```

Perform any changes, then execute:

```
$this->assertEquals($q, $this->getDB('user'));
```

To ensure that database remained the same. Of course you can compare
against any other state. 

- Automatically add 'id' field by default
- Create tables for you
- Detect types (int, string, etc)
- Hides ID values if you don't pass them

## Installation

Add the following inside your `composer.json` file:

``` console
composer require atk4/schema
```

## Current Status

Early development

