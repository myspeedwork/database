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

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
abstract class MigrationAbstract
{
    protected $schema;

    /**
     * Set Schema object.
     *
     * @param \Doctrine\DBAL\Schema\Schema $schema
     */
    public function setSchema(BaseSchema $schema, $prefix = null)
    {
        $this->schema = new Schema($schema);
        $this->schema->setTablePrefix($prefix);
    }

    /**
     * Get Schema Object.
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    protected function getSchema()
    {
        return $this->schema;
    }

    /**
     * Run 'up' migration.
     *
     * @param Schema $schema
     */
    abstract public function up();

    /**
     * Run 'down' migration.
     *
     * @param Schema $schema
     */
    abstract public function down();
}
