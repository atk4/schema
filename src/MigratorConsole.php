<?php

namespace atk4\schema;

/**
 * Makes sure your database is adjusted for one or serveral models,
 * that you specify.
 */
class MigratorConsole extends \atk4\ui\Console
{
    /**
     * Provided with array of models, perform migration for each of them.
     */
    public function migrateModels($models)
    {
        // run inside callback
        $this->set(function ($c) use ($models) {
            $c->notice('Preparing to migrate models');
            $p = $c->app->db;

            foreach ($models as $model) {
                if (!is_object($model)) {
                    $model = $this->factory($model);
                    $p->add($model);
                }

                $m = new \atk4\schema\Migration\MySQL($model);
                $result = $m->migrate();

                $c->debug('  '.get_class($model).'.. '.$result);
            }

            $c->notice('Done with migration');
        });
    }
    
    /**
     * Single file creation of ClassFile using DB Table
     */
    public function createModelClass(Persistence_SQL $connection, $tableName, $futureModelName, $id_field = 'id', $ClassNamespace = '\Your\Project\Models')
    {
        $this->set(function ($c) use ($connection, $tableName, $futureModelName, $id_field, $ClassNamespace) {
    
            $c->notice('Start create class for table :' . $tableName);
    
            $m = new \atk4\schema\Migration\MySQL($connection);
    
            $output = $m->createModelFromTable($tableName, $futureModelName, $id_field, $ClassNamespace);
    
            $c->debug($output);
        });
    }
}
