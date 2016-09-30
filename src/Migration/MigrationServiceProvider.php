<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database\Migration;

use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

/**
 * Speedwork database migration service Provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class MigrationServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app['migration.repository'] = function ($app) {
            return new MigrationRepository($app);
        };

        $app['migrator'] = function ($app) {
            $migrator = new Migrator($app['migration.repository'], $app);
            $migrator->path($app['config']->get('database.migrations'));

            return $migrator;
        };

        $app['migration.creator'] = function ($app) {
            return new MigrationCreator($app['fs']);
        };

        $commands = [
            'console.migration.install' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\InstallCommand',
                'argv'  => ['app.migrator'],
            ],
            'console.migrate' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\MigrateCommand',
                'argv'  => ['app.migrator'],
            ],
            'console.migration.refresh' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\RefreshCommand',
            ],
            'console.migrate.rollback' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\RollbackCommand',
                'argv'  => ['app.migrator'],
            ],
            'console.migrate.reset' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\ResetCommand',
                'argv'  => ['app.migrator'],
            ],
            'console.migrate.status' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\StatusCommand',
                'argv'  => ['app.migrator'],
            ],
            'console.migrate.make' => [
                'class' => '\\Speedwork\\Database\\Migration\\Console\\MigrateMakeCommand',
                'argv'  => ['app.migration.creator'],
            ],
        ];

        $app['console.register']($commands);
    }
}
