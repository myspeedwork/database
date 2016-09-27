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

class BaseCommand extends Command
{
    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath()
    {
        return $this->app['path.database'].DIRECTORY_SEPARATOR.'migrations';
    }

    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        // Here, we will check to see if a path option has been defined. If it has
        // we will use the path relative to the root of this installation folder
        // so that migrations may be run for any path within the applications.
        if ($this->input->hasOption('path') && $this->option('path')) {
            return [$this->app['path.base'].DIRECTORY_SEPARATOR.$this->option('path')];
        }

        return array_merge(
            [$this->getMigrationPath()], $this->migrator->paths()
        );
    }
}
