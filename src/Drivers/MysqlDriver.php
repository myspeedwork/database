<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Speedwork\Database\Driver;

use Speedwork\Database\DboSource;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class MysqlDriver extends DboSource
{
    /**
     * Reference to the PDO object connection.
     *
     * @var PDO
     */
    protected $_connection = null;

    /**
     * Start quote.
     *
     * @var string
     */
    public $startQuote = '`';

    /**
     * End quote.
     *
     * @var string
     */
    public $endQuote = '`';

    /**
     * Base configuration settings for MySQL driver.
     *
     * @var array
     */
    public $_baseConfig = [
        'persistent' => true,
        'host'       => 'localhost',
        'login'      => 'root',
        'password'   => '',
        'database'   => 'logics',
        'port'       => '3306',
        'encoding'   => '',
        'timezome'   => '',
    ];

    /**
     * Connects to the database using options in the given configuration array.
     *
     * @return bool True if the database could be connected, else false
     */
    public function connect()
    {
        $config = $this->config;
        $config = @array_merge($this->_baseConfig, $config);

        $this->connected = false;

        if (!$config['persistent']) {
            $this->connection  = @mysql_connect($config['host'], $config['login'], $config['password'], true);
            $config['connect'] = 'mysql_connect';
        } else {
            $this->connection = @mysql_pconnect($config['host'].':'.$config['port'], $config['login'], $config['password']);
        }

        if (!$this->connection) {
            return false;
        }

        if (mysql_select_db($config['database'], $this->connection)) {
            $this->connected = true;
        }

        if (!empty($config['encoding'])) {
            $this->setEncoding($config['encoding']);
        }

        if (!empty($config['timezone'])) {
            $this->setTimezone($config['timezone']);
        }

        //$this->_useAlias = (bool)version_compare(mysql_get_server_info($this->connection), "4.1", ">=");

        return $this->connection;
    }

    /**
     * Check whether the MySQL extension is installed/loaded.
     *
     * @return bool
     */
    public function enabled()
    {
        return extension_loaded('mysql');
    }

    /**
     * Disconnects from database.
     *
     * @return bool True if the database could be disconnected, else false
     */
    public function disconnect()
    {
        if (isset($this->results) && is_resource($this->results)) {
            mysql_free_result($this->results);
        }
        $this->connected = !@mysql_close($this->connection);

        return !$this->connected;
    }

    public function fetch($sql)
    {
        $data          = [];
        $this->_result = mysql_query($sql, $this->connection);

        while ($row = @mysql_fetch_array($this->_result, MYSQL_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Load a list of database rows (numeric column indexing).
     *
     *
     * @param string The field name of a primary key
     *
     * @return array If <var>key</var> is empty as sequential list of returned records.
     *               If <var>key</var> is not empty then the returned array is indexed by the value
     *               the database key.  Returns <var>null</var> if the query fails.
     */
    public function fetchRow($sql, $key = null)
    {
        if (!$sql) {
            return;
        }
        $this->_result = mysql_query($sql, $this->connection);
        $array         = [];
        while ($row = mysql_fetch_row($this->_result)) {
            if ($key !== null) {
                $array[$row[$key]] = $row;
            } else {
                $array[] = $row;
            }
        }
        mysql_free_result($this->_result);

        return $array;
    }

    /**
     * Executes given SQL statement.
     *
     * @param string $sql SQL statement
     *
     * @return resource Result resource identifier
     */
    public function query($sql)
    {
        if (preg_match('/^\s*call/i', $sql)) {
            return $this->_executeProcedure($sql);
        }

        return mysql_query($sql, $this->connection);
    }

    /**
     * Executes given SQL statement (procedure call).
     *
     * @param string $sql SQL statement (procedure call)
     *
     * @return resource Result resource identifier for first recordset
     */
    public function _executeProcedure($sql)
    {
        $answer = mysql_multi_query($this->connection, $sql);

        $firstResult = mysql_store_result($this->connection);

        if (mysql_more_results($this->connection)) {
            while ($lastResult = mysql_next_result($this->connection));
        }

        return $firstResult;
    }

    /**
     * Returns a formatted error message from previous database operation.
     *
     * @return string Error message with error number
     */
    public function lastError()
    {
        if (mysql_errno($this->connection)) {
            return mysql_errno($this->connection).': '.mysql_error($this->connection);
        }

        return;
    }

    /**
     * Returns the ID generated from the previous INSERT operation.
     *
     * @param unknown_type $source
     *
     * @return in
     */
    public function insertId()
    {
        return @mysql_insert_id($this->connection);
    }

    /**
     * Returns number of affected rows in previous database operation. If no previous operation exists,
     * this returns false.
     *
     * @return int Number of affected rows
     */
    public function affectedRows()
    {
        if ($this->_result) {
            return mysql_affected_rows($this->connection);
        }

        return;
    }

    /**
     * Returns number of rows in previous resultset. If no previous resultset exists,
     * this returns false.
     *
     * @return int Number of rows in resultset
     */
    public function numRows()
    {
        if ($this->_result) {
            return mysql_num_rows($this->_result);
        }

        return;
    }

    /**
     * Sets the database encoding.
     *
     * @param string $enc Database encoding
     */
    public function setEncoding($enc)
    {
        return $this->query('SET NAMES '.$enc) != false;
    }

    /**
     * Sets the database timezone.
     *
     * @param string $zone Database timezone
     */
    public function setTimezone($zone)
    {
        return $this->query('SET time_zone = '.$zone) != false;
    }

    /**
     * Gets the database encoding.
     *
     * @return string The database encoding
     */
    public function getEncoding()
    {
        return mysql_client_encoding($this->connection);
    }

    /**
     * Description.
     *
     *
     * @return array A list of all the tables in the database
     */
    public function getTableList()
    {
        return $this->fetchRow('SHOW TABLES');
    }

    /**
     * Shows the CREATE TABLE statement that creates the given tables.
     *
     *
     * @param 	array|string 	A table name or a list of table names
     *
     * @return array A list the create SQL for the tables
     */
    public function getTableCreate($tables)
    {
        settype($tables, 'array'); //force to array
        $result = [];

        foreach ($tables as $tblval) {
            $sql  = 'SHOW CREATE table '.$this->securesql($tblval);
            $rows = $this->fetchRow($sql);
            foreach ($rows as $row) {
                $result[$tblval] = $row[1];
            }
        }

        return $result;
    }

    /**
     * Retrieves information about the given tables.
     *
     *
     * @param 	array|string 	A table name or a list of table names
     * @param	bool			Only return field types, default true
     *
     * @return array An array of fields by table
     */
    public function getTableFields($tables, $typeonly = true)
    {
        settype($tables, 'array'); //force to array
        $result = [];

        foreach ($tables as $tblval) {
            $fields = $this->fetch('SHOW FIELDS FROM '.$tblval);

            if ($typeonly) {
                foreach ($fields as $field) {
                    $result[$tblval][$field['Field']] = preg_replace('/[(0-9)]/', '', $field['Type']);
                }
            } else {
                foreach ($fields as $field) {
                    $result[$tblval][] = $field;
                }
            }
        }

        return $result;
    }

    /**
     * Deletes all the records in a table and resets the count of the auto-incrementing
     * primary key, where applicable.
     *
     * @param mixed $table A string or model class representing the table to be truncated
     *
     * @return bool SQL TRUNCATE TABLE statement, false if not applicable.
     */
    public function truncate($table)
    {
        return $this->query('TRUNCATE TABLE '.$table);
    }

    /**
     * Helper function to clean the incoming values.
     **/
    public function securesql($str)
    {
        $str = trim($str);
        if ($str == '') {
            return;
        }

        if (get_magic_quotes_gpc()) {
            return $str;
        }

        if (function_exists('mysql_real_escape_string')) {
            $str = mysql_real_escape_string($str);
        } else {
            $str = addslashes($str);
        }

        return $str;
    }
}
