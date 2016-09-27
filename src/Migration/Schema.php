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

use Doctrine\DBAL\Schema\Schema as BaseSchema;

class Schema extends BaseSchema
{
    protected $tablePrefix = '';

    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getTable($tableName)
    {
        $tableName = str_replace('#__', $this->tablePrefix, $tableName);

        return parent::getTable($tableName);
    }
}
