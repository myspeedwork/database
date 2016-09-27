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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Speedwork\Container\Container;

class Migrator
{
    /**
     * Application Instance.
     *
     * @var \Speedwork\Container\Container
     */
    protected $app;

    /**
     * Schema object.
     *
     * @var \Doctrine\DBAL\Schema\Schema
     */
    protected $scheme;

    /**
     * Migrations table name.
     *
     * @var string
     */
    protected $table = 'migrations';

    /**
     * Table prefix for databse.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;

    /**
     * The notes for the current operation.
     *
     * @var array
     */
    protected $notes = [];

    /**
     * The paths to all of the migration files.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * The migration repository implementation.
     *
     * @var \Speedwork\Database\Migration\MigrationRepository
     */
    protected $repository;

    protected $clone;

    public function __construct(MigrationRepository $repository, Container $app)
    {
        $this->app        = $app;
        $this->repository = $repository;
        $this->path($app['path.database'].'migrations');

        $this->setConnection();
    }

    public function getMigrationFiles($paths = [])
    {
        // Clean paths
        foreach ($paths as &$path) {
            $path = str_replace(['/', '//', '\\', '\\\\'],
                DIRECTORY_SEPARATOR,
                rtrim($path, '/')
            );
        }

        $paths = array_unique($paths);

        $files = $this->app['finder']
                ->files()
                ->followLinks()
                ->name('*.php')
                ->in($paths)
                ->sortByName();

        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = $file->getRealPath();
        }

        return array_unique($migrations);
    }

    /**
     * Register a custom migration path.
     *
     * These path will not automatically be applied.
     *
     * @param string $path
     */
    public function path($path)
    {
        if (is_array($path)) {
            $this->paths = array_merge($this->paths, $path);
        } else {
            $this->paths[] = $path;
        }

        $this->paths = array_unique($this->paths);
    }

    /**
     * Get all of the custom migration paths.
     *
     * @return array
     */
    public function paths()
    {
        return $this->paths;
    }

    protected function setSchema()
    {
        $this->clone = clone $this->schema;
    }

    protected function getSchema()
    {
        return $this->clone;
    }

    /**
     * Run the outstanding migrations at a given path.
     *
     * @param array|string $paths
     * @param array        $options
     *
     * @return array
     */
    public function run($paths = [], array $options = [])
    {
        $this->notes = [];

        $paths = array_merge($this->paths, $paths);
        $files = $this->getMigrationFiles($paths);

        if (empty($files)) {
            return $this->note('<info>Nothing to migrate.</info>');
        }

        $migrated = $this->repository->getMigrations();

        $migrations = [];
        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            if (!in_array($name, $migrated)) {
                $migrations[] = $file;
            }
        }

        $this->requireFiles($migrations);

        $this->up($migrations, $options);

        return $migrations;
    }

    protected function up($migrations, array $options = [])
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) == 0) {
            $this->note('<info>Nothing to migrate.</info>');

            return;
        }

        $batch = $this->repository->getNextBatchNumber();
        $step  = $options['step'] ?: false;

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $name = $this->getMigrationName($file);

            // First we will resolve a "real" instance of the migration class from this
            // migration file name. Once we have the instances we can run the actual
            // command such as "up" or "down", or we can just simulate the action.
            $instance = $this->resolve($name);
            $instance->setSchema($this->getSchema(), $this->tablePrefix);
            $instance->up();

            $this->build();

            // Once we have run a migrations class, we will log that it was run in this
            // repository so that we don't try to run it next time we do a migration
            // in the application. A migration repository keeps the migrate order.
            $this->repository->log($name, $batch);

            $this->note("<info>Migrated:</info> {$name}");

            // If we are stepping through the migrations, then we will increment the
            // batch value for each individual migration that is run. That way we
            // can run "console migrate:rollback" and undo them one at a time.
            if ($step) {
                ++$batch;
            }
        }
    }

    /**
     * Rollback the last migration operation.
     *
     * @param array|string $paths
     * @param array        $options
     *
     * @return array
     */
    public function rollback($paths = [], array $options = [])
    {
        $step = $options['step'] ?: 0;

        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        if ($step > 0) {
            $migrations = $this->repository->getMigrations($step);
        } else {
            $migrations = $this->repository->getLast();
        }

        return $this->runRollback($paths, $migrations);
    }

    /**
     * Rolls all of the currently applied migrations back.
     *
     * @param array|string $paths
     * @param bool         $pretend
     *
     * @return array
     */
    public function reset($paths = [])
    {
        // Next, we will reverse the migration list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the migrations.
        $migrations = array_reverse($this->repository->getMigrations());

        return $this->runRollback($paths, $migrations);
    }

    /**
     * Rollback the last migration operation.
     *
     * @param array|string $paths
     * @param array        $options
     *
     * @return array
     */
    protected function runRollback($paths = [], array $migrations = [])
    {
        $this->notes = [];
        $rollback    = [];

        $count = count($migrations);
        $files = $this->getMigrationFiles($paths);

        if ($count === 0) {
            $this->note('<info>Nothing to rollback.</info>');
        } else {
            // Next we will run through all of the migrations and call the "down" method
            // which will reverse each migration in order. This getLast method on the
            // repository already returns these migration's names in reverse order.
            $this->requireFiles($files);

            foreach ($migrations as $migration) {
                $rollback[] = $migration;

                $name = $this->getMigrationName($migration);

                // First we will get the file name of the migration so we can resolve out an
                // instance of the migration. Once we get an instance we can either run a
                // pretend execution of the migration or we can run the real migration.
                $instance = $this->resolve($name);
                $instance->setSchema($this->getSchema(), $this->tablePrefix);
                $instance->down();

                $this->build();

                // Once we have successfully run the migration "down" we will remove it from
                // the migration repository so it will be considered to have not been run
                // by the application then will be able to fire by any later operation.
                $this->repository->delete($migration);

                $this->note("<info>Rolled back:</info> {$name}");
            }
        }

        return $rollback;
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param array $files
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            require_once $file;
        }
    }

    protected function build()
    {
        $queries = $this->schema->getMigrateToSql($this->getSchema(), $this->connection->getDatabasePlatform());

        foreach ($queries as $query) {
            $query = str_replace('#__', $this->tablePrefix, $query);
            $this->connection->exec($query);
        }

        //$this->setSchema();
        $this->setConnection();
    }

    /**
     * Create the migration repository data store.
     */
    public function createTable()
    {
        $schema = $this->getSchema();
        $table  = $schema->createTable($this->table);

        $table->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $table->addColumn('migration', 'string');
        $table->addColumn('batch', 'integer');
        $table->setPrimaryKey(['id']);

        $this->build();
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function tableExists()
    {
        $schema = $this->getSchema();

        return $schema->hasTable($this->table);
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param string $file
     *
     * @return object
     */
    public function resolve($file)
    {
        $class = str_replace(' ', '', ucwords(implode(' ', array_slice(explode('_', $file), 4))));

        return new $class();
    }

    /**
     * Get the name of the migration.
     *
     * @param string $path
     *
     * @return string
     */
    public function getMigrationName($path)
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Raise a note event for the migrator.
     *
     * @param string $message
     */
    protected function note($message)
    {
        $this->notes[] = $message;
    }

    /**
     * Get the notes for the last operation.
     *
     * @return array
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     */
    public function setConnection($name = null)
    {
        $config      = $this->app['config']->get('database');
        $connections = $config['connections'];
        $name        = $name ?: $config['default'];
        $params      = $connections[$name];

        $this->tablePrefix = $params['prefix'];

        $drivers = [
            'mysqli' => 'mysqli',
            'sqlite' => 'pdo_sqlite',
            'pgsql'  => 'pdo_pgsql',
        ];

        $connParams = [
            'dbname'   => $params['database'],
            'user'     => $params['username'],
            'password' => $params['password'],
            'host'     => $params['host'],
            'driver'   => $drivers[$params['driver']],
        ];

        $this->connection = DriverManager::getConnection($connParams);

        $this->schema = $this->connection->getSchemaManager()->createSchema();

        $this->repository->setSource($name);
        $this->setSchema();
    }

    /**
     * Get the migration repository instance.
     *
     * @return \Speedwork\Database\Migration\MigrationRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }
}
