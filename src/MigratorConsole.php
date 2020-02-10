<?php

namespace atk4\schema;

/**
 * Makes sure your database is adjusted for one or several models,
 * that you specify.
 */
class MigratorConsole extends \atk4\ui\Console
{
    /** @var string Name of migrator class to use */
    public $migrator_class = Migration\Mysql::class;

    /**
     * Provided with array of models, perform migration for each of them.
     *
     * @param array $models
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

                $m = new $this->migrator_class($model);
                $result = $m->migrate();

                $c->debug('  ' . get_class($model) . '.. ' . $result);
            }

            $c->notice('Done with migration');
        });
    }
}
