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
use Speedwork\Console\Traits\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;

class RefreshCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'migrate:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all migrations';

    /**
     * Execute the console command.
     */
    public function fire()
    {
        if (!$this->confirmToProceed()) {
            return;
        }

        $database = $this->input->getOption('database');

        $force = $this->input->getOption('force');

        $path = $this->input->getOption('path');

        // If the "step" option is specified it means we only want to rollback a small
        // number of migrations before migrating again. For example, the user might
        // only rollback and remigrate the latest four migrations instead of all.
        $step = $this->input->getOption('step') ?: 0;

        if ($step > 0) {
            $this->call('migrate:rollback', [
                '--database' => $database, '--force' => $force, '--step' => $step,
            ]);
        } else {
            $this->call('migrate:reset', [
                '--database' => $database, '--force' => $force,
            ]);
        }

        // The refresh command is essentially just a brief aggregate of a few other of
        // the migration commands and just provides a convenient wrapper to execute
        // them in succession. We'll also see if we need to re-seed the database.
        $this->call('migrate', [
            '--database' => $database,
            '--force'    => $force,
            '--path'     => $path,
        ]);
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

            ['path', null, InputOption::VALUE_OPTIONAL, 'The path of migrations files to be executed.'],

            ['step', null, InputOption::VALUE_OPTIONAL, 'The number of migrations to be reverted & re-run.'],
        ];
    }
}
