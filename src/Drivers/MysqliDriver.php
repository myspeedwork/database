<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Database\Drivers;

use Speedwork\Database\DboSource;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class MysqliDriver extends DboSource
{
    public $connection;
    public $config = [];

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
     * No of attemts to reconnect.
     *
     * @var int
     */
    protected $attempts = 3;

    /**
     * Base configuration settings for MySQL driver.
     *
     * @var array
     */
    public $_baseConfig = [
        'persistent' => true,
        'host'       => 'localhost',
        'username'   => 'root',
        'password'   => '',
        'database'   => 'logics',
        'port'       => '3306',
        'charset'    => '',
        'timezone'   => '',
    ];

    /**
     * Connects to the database using options in the given configuration array.
     *
     * @return bool True if the database could be connected, else false
     */
    public function connect()
    {
        $config = $this->config;

        $config          = @array_merge($this->_baseConfig, $config);
        $this->connected = false;

        if (is_numeric($config['port'])) {
            $config['socket'] = null;
        } else {
            $config['socket'] = $config['port'];
            $config['port']   = null;
        }

        $this->connection = @mysqli_connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);

        if ($this->connection !== false) {
            $this->connected = true;
        }

        if (!empty($config['charset'])) {
            $this->setEncoding($config['charset']);
        }

        if (!empty($config['timezone'])) {
            $this->setTimezone($config['timezone']);
        }

        return $this->connection;
    }

    /**
     * Check whether the MySQL extension is installed/loaded.
     *
     * @return bool
     */
    public function enabled()
    {
        return extension_loaded('mysqli');
    }

    /**
     * Disconnects from database.
     *
     * @return bool True if the database could be disconnected, else false
     */
    public function disconnect()
    {
        if (isset($this->results) && is_resource($this->results)) {
            mysqli_free_result($this->results);
        }
        $this->connected = !@mysqli_close($this->connection);

        return !$this->connected;
    }

    public function fetch($sql)
    {
        $data          = [];
        $this->_result = $this->query($sql);

        while ($row = @mysqli_fetch_array($this->_result, MYSQLI_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Returns a formatted error message from previous database operation.
     *
     * @return string Error message with error number
     */
    public function lastError()
    {
        if ($this->connection && mysqli_errno($this->connection)) {
            return mysqli_errno($this->connection).': '.mysqli_error($this->connection);
        }

        return;
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
        //if (preg_match('/^\s*call/i', $sql)) {
        //	return $this->_executeProcedure($sql);
        //}
        $result = @mysqli_query($this->connection, $sql);
        if (!$result) {
            $code  = @mysqli_errno($this->connection);
            $codes = [2006];
            if (in_array($code, $codes) && $this->attempts > 0) {
                --$this->attempts;
                $this->disconnect();
                $this->connect();

                return $this->query($sql);
            }
        }

        return $result;
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
        return @mysqli_insert_id($this->connection);
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
            return mysqli_affected_rows($this->connection);
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
            return mysqli_num_rows($this->_result);
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
        return mysqli_client_encoding($this->connection);
    }

    /**
     * Helper function to clean the incoming values.
     **/
    public function securesql($str)
    {
        if ($str == '') {
            return;
        }

        if (function_exists('mysqli_real_escape_string')) {
            $str = mysqli_real_escape_string($this->connection, $str);
        } else {
            $str = addslashes($str);
        }

        return trim($str);
    }
}
