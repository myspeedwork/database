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

use Speedwork\Console\Command;
use Speedwork\Database\Migration\Migrator;
use Symfony\Component\Console\Input\InputOption;

class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the migration database';

    /**
     * The migration instance.
     *
     * @var \Speedwork\Database\Migration\Migrator
     */
    protected $migrator;

    /**
     * Create a new migration install command instance.
     *
     * @param \Speedwork\Database\Migration\Migrator $migrator
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
        $this->migrator->setConnection($this->input->getOption('database'));

        if ($this->migrator->tableExists()) {
            $this->warn('Migration table already exits.');
        } else {
            $this->migrator->createTable();

            $this->info('Migration table created successfully.');
        }
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
        ];
    }
}
