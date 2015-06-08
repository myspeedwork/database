<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Speedwork\Database;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class DataSource
{
    /**
     * Are we connected to the DataSource?
     *
     * @var bool
     */
    public $connected = false;

    /**
     * The default configuration of a specific DataSource.
     *
     * @var array
     */
    protected $_baseConfig = [];

    /**
     * Holds references to descriptions loaded by the DataSource.
     *
     * @var array
     */
    protected $_descriptions = [];

    /**
     * Holds a list of sources (tables) contained in the DataSource.
     *
     * @var array
     */
    protected $_sources = null;

    /**
     * The DataSource configuration.
     *
     * @var array
     */
    public $config = [];

    /**
     * Whether or not this DataSource is in the middle of a transaction.
     *
     * @var bool
     */
    protected $_transactionStarted = false;

    /**
     * Whether or not source data like available tables and schema descriptions
     * should be cached.
     *
     * @var bool
     */
    public $cacheSources = true;

    /**
     * Constructor.
     *
     * @param array $config Array of configuration information for the datasource.
     */
    public function __construct($config = [])
    {
        $this->setConfig($config);
    }

    /**
     * Begin a transaction.
     *
     * @return bool Returns true if a transaction is not in progress
     */
    public function begin()
    {
        return !$this->_transactionStarted;
    }

    /**
     * Commit a transaction.
     *
     * @return bool Returns true if a transaction is in progress
     */
    public function commit()
    {
        return $this->_transactionStarted;
    }

    /**
     * Rollback a transaction.
     *
     * @return bool Returns true if a transaction is in progress
     */
    public function rollback()
    {
        return $this->_transactionStarted;
    }

    /**
     * Converts column types to basic types.
     *
     * @param string $real Real column type (i.e. "varchar(255)")
     *
     * @return string Abstract column type (i.e. "string")
     */
    public function column($real)
    {
        return false;
    }

    /**
     * Used to create new records. The "C" CRUD.
     *
     * To-be-overridden in subclasses.
     *
     * @param Model $model  The Model to be created.
     * @param array $fields An Array of fields to be saved.
     * @param array $values An Array of values to save.
     *
     * @return bool success
     */
    public function create(Model $model, $fields = null, $values = null)
    {
        return false;
    }

    /**
     * Used to read records from the Datasource. The "R" in CRUD.
     *
     * To-be-overridden in subclasses.
     *
     * @param Model $model     The model being read.
     * @param array $queryData An array of query data used to find the data you want
     * @param int   $recursive Number of levels of association
     *
     * @return mixed
     */
    public function read(Model $model, $queryData = [], $recursive = null)
    {
        return false;
    }

    /**
     * Update a record(s) in the datasource.
     *
     * To-be-overridden in subclasses.
     *
     * @param Model $model      Instance of the model class being updated
     * @param array $fields     Array of fields to be updated
     * @param array $values     Array of values to be update $fields to.
     * @param mixed $conditions
     *
     * @return bool Success
     */
    public function update(Model $model, $fields = null, $values = null, $conditions = null)
    {
        return false;
    }

    /**
     * Delete a record(s) in the datasource.
     *
     * To-be-overridden in subclasses.
     *
     * @param Model $model      The model class having record(s) deleted
     * @param mixed $conditions The conditions to use for deleting.
     *
     * @return bool Success
     */
    public function delete(Model $model, $id = null)
    {
        return false;
    }

    /**
     * Returns the ID generated from the previous INSERT operation.
     *
     * @param mixed $source
     *
     * @return mixed Last ID key generated in previous INSERT
     */
    public function lastInsertId($source = null)
    {
        return false;
    }

    /**
     * Returns the number of rows returned by last operation.
     *
     * @param mixed $source
     *
     * @return int Number of rows returned by last operation
     */
    public function lastNumRows($source = null)
    {
        return false;
    }

    /**
     * Returns the number of rows affected by last query.
     *
     * @param mixed $source
     *
     * @return int Number of rows affected by last query.
     */
    public function lastAffected($source = null)
    {
        return false;
    }

    /**
     * Check whether the conditions for the Datasource being available
     * are satisfied. Often used from connect() to check for support
     * before establishing a connection.
     *
     * @return bool Whether or not the Datasources conditions for use are met.
     */
    public function enabled()
    {
        return true;
    }

    /**
     * Sets the configuration for the DataSource.
     * Merges the $config information with the _baseConfig and the existing $config property.
     *
     * @param array $config The configuration array
     */
    public function setConfig($config = [])
    {
        $this->config = array_merge($this->_baseConfig, $this->config, $config);
    }

    /**
     * Returns the schema name. Override this in subclasses.
     *
     * @return string schema name
     */
    public function getSchemaName()
    {
        return;
    }

    /**
     * Closes a connection. Override in subclasses.
     *
     * @return bool
     */
    public function close()
    {
        return $this->connected = false;
    }

    /**
     * Closes the current datasource.
     */
    public function __destruct()
    {
        if ($this->_transactionStarted) {
            $this->rollback();
        }
        if ($this->connected) {
            $this->close();
        }
    }
}
