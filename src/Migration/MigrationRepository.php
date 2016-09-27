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

class MigrationRepository
{
    protected $app;
    /**
     * The name of the migration table.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $database;

    public function __construct(Container $app, $table = 'migrations')
    {
        $this->app   = $app;
        $this->table = $table;
        $this->setSource();
    }

    /**
     * Get list of migrations.
     *
     * @param int $limit
     *
     * @return array
     */
    public function getMigrations($limit = null)
    {
        if (empty($limit)) {
            return $this->database->find($this->table, 'list', [
                'fields' => ['migration'],
                'order'  => ['batch ASC', 'migration ASC'],
            ]);
        }

        return $this->database->find($this->table, 'list', [
            'fields'     => ['migration'],
            'order'      => ['migration DESC'],
            'conditions' => ['batch >= 1'],
            'limit'      => $limit,
        ]);
    }

    /**
     * Get the last migration batch.
     *
     * @return array
     */
    public function getLast()
    {
        return $this->database->find($this->table, 'list', [
            'conditions' => ['batch' => $this->getLastBatchNumber()],
            'order'      => ['migration DESC'],
            'fields'     => ['migration'],
        ]);
    }

    /**
     * Log that a migration was run.
     *
     * @param string $file
     * @param int    $batch
     */
    public function log($file, $batch)
    {
        $record = ['migration' => $file, 'batch' => $batch];

        $this->database->save($this->table, $record);
    }

    /**
     * Remove a migration from the log.
     *
     * @param object $migration
     */
    public function delete($migration)
    {
        $this->database->delete($this->table, ['migration' => $migration]);
    }

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber()
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number.
     *
     * @return int
     */
    public function getLastBatchNumber()
    {
        $row = $this->database->find($this->table, 'first', [
            'fields' => ['batch'],
            'order'  => ['batch DESC'],
        ]);

        return $row['batch'];
    }

    public function setSource($name = null)
    {
        if ($name) {
            $this->database = $this->app['database.'.$name];
        } else {
            $this->database = $this->app['database'];
        }
    }
}
