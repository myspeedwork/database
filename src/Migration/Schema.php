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

class Schema
{
    protected $tablePrefix = '';

    protected $schema;

    public function __construct(BaseSchema $schema)
    {
        $this->schema = $schema;
    }

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

        return $this->schema->getTable($tableName);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->schema, $method], $args);
    }
}
