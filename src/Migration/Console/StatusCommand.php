<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database\Migration\Console;

use Speedwork\Database\Migration\Migrator;
use Symfony\Component\Console\Input\InputOption;

class StatusCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each migration';

    /**
     * The migrator instance.
     *
     * @var \Speedwork\Database\Migration\Migrator
     */
    protected $migrator;

    /**
     * Create a new migration rollback command instance.
     *
     * @param \Speedwork\Database\Migration\Migrator $migrator
     *
     * @return \Speedwork\Database\Migration\Console\StatusCommand
     */
    public function __construct(Migrator $migrator)
    {
        parent::__construct();

        $this->migrator = $migrator;
    }

    /**
     * Execute the console command.
     */
    public function fire()
    {
        $this->migrator->setConnection($this->option('database'));

        if (!$this->migrator->tableExists()) {
            return $this->error('No migrations found.');
        }

        $migrations = $this->migrator->getRepository()->getMigrations();
        $files      = $this->getAllMigrationFiles();

        $list = [];

        foreach ($files as $file) {
            $name   = $this->migrator->getMigrationName($file);
            $list[] = in_array($name, $migrations)
                        ? ['<info>Y</info>', $name]
                        : ['<fg=red>N</fg=red>', $name];
        }

        if (count($list) > 0) {
            $this->table(['Ran?', 'Migration'], $list);
        } else {
            $this->error('No migrations found');
        }
    }

    /**
     * Get an array of all of the migration files.
     *
     * @return array
     */
    protected function getAllMigrationFiles()
    {
        return $this->migrator->getMigrationFiles($this->getMigrationPaths());
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],

            ['path', null, InputOption::VALUE_OPTIONAL, 'The path of migrations files to use.'],
        ];
    }
}
