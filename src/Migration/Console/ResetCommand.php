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

use Speedwork\Console\Traits\ConfirmableTrait;
use Speedwork\Database\Migration\Migrator;
use Symfony\Component\Console\Input\InputOption;

class ResetCommand extends BaseCommand
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all database migrations';

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
        if (!$this->confirmToProceed()) {
            return;
        }

        $this->migrator->setConnection($this->option('database'));

        // First, we'll make sure that the migration table actually exists before we
        // start trying to rollback and re-run all of the migrations. If it's not
        // present we will just bail out with a info message for the developer.
        if (!$this->migrator->tableExists()) {
            return $this->comment('Migration table not found.');
        }

        $this->migrator->reset(
            $this->getMigrationPaths()
        );

        // Once the migrator has run we will grab the note output and send it out to
        // the console screen, since the migrator itself functions without having
        // any instances of the OutputInterface contract passed into the class.
        foreach ($this->migrator->getNotes() as $note) {
            $this->output->writeln($note);
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

            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'],
        ];
    }
}
