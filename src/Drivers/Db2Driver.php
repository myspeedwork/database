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
class Db2Driver extends DboSource
{
    /**
     * Starting Quote.
     *
     * @var string
     */
    protected $startQuote = '';

    /**
     * Ending Quote.
     *
     * @var string
     */
    protected $endQuote = '';

    /**
     * Base configuration settings for MySQL driver.
     *
     * @var array
     */
    protected $_baseConfig = [
        'persistent' => true,
        'host'       => 'localhost',
        'username'   => 'root',
        'password'   => '',
        'database'   => 'logics',
        'schema'     => 'public',
        'port'       => 50000,
        'charset'    => '',
    ];

    /**
     * Connects to the database using options in the given configuration array.
     *
     * @return bool True if the database could be connected, else false
     */
    public function connect()
    {
        $config = $this->config;
        $config = array_merge($this->_baseConfig, $config);

        $conn = "DATABASE='{$config['database']}';HOSTNAME='{$config['host']}';PORT={$config['port']};";
        $conn .= "PROTOCOL=TCPIP;UID={$config['username']};PWD={$config['password']};";

        if (!$config['persistent']) {
            $this->connection = db2_connect($conn, PGSQL_CONNECT_FORCE_NEW);
        } else {
            $this->connection = db2_pconnect($conn);
        }
        $this->connected = false;

        if ($this->connection) {
            $this->connected = true;
            $this->query('SET search_path TO '.$config['schema']);
        }
        if (!empty($config['charset'])) {
            $this->setEncoding($config['charset']);
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
        return extension_loaded('ibm_db2');
    }

    /**
     * Disconnects from database.
     *
     * @return bool True if the database could be disconnected, else false
     */
    public function disconnect()
    {
        if ($this->_result) {
            db2_free_result($this->_result);
        }
        if (is_resource($this->connection)) {
            $this->connected = !db2_close($this->connection);
        } else {
            $this->connected = false;
        }

        return !$this->connected;
    }

    public function fetch($sql)
    {
        $data          = [];
        $stmt          = db2_prepare($this->connection, $sql);
        $this->_result = db2_execute($stmt);

        while ($row = db2_fetch_assoc($stmt)) {
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
        $error = db2_conn_errormsg($this->connection);

        return ($error) ? $error : null;
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
        $stmt = db2_prepare($this->connection, $sql);
        $res  = db2_execute($stmt);

        return $res;
    }

    /**
     * Returns the ID generated from the previous INSERT operation.
     *
     * @param unknown_type $source
     *
     * @return in
     */
    public function lastInsertId()
    {
        return @db2_last_lastInsertId($this->connection);
    }

    /**
     * Returns number of affected rows in previous database operation. If no previous operation exists,
     * this returns false.
     *
     * @return int Number of affected rows
     */
    public function lastAffected()
    {
        return ($this->_result) ? db2_num_rows($this->_result) : false;
    }

    /**
     * Returns number of rows in previous resultset. If no previous resultset exists,
     * this returns false.
     *
     * @return int Number of rows in resultset
     */
    public function lastNumRows()
    {
        return ($this->_result) ? db2_num_rows($this->_result) : false;
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
     * Gets the database encoding.
     *
     * @return string The database encoding
     */
    public function getEncoding()
    {
        return pg_client_encoding($this->connection);
    }

    /**
     * Returns a limit statement in the correct format for the particular database.
     *
     * @param int $limit  Limit of results returned
     * @param int $offset Offset from which to start results
     *
     * @return string SQL limit/offset statement
     */
    public function limit($limit, $offset = null, $page = null)
    {
        if ($limit) {
            $rt = '';
            if (!strpos(strtolower($limit), 'limit') || strpos(strtolower($limit), 'limit') === 0) {
                $rt = ' LIMIT';
            }

            $rt .= ' '.$limit;
            if (intval($page) && !$offset) {
                $offset = $limit * ($page - 1);
            }

            if ($offset) {
                $rt .= ' OFFSET '.$offset;
            }

            return $rt;
        }

        return;
    }

    /**
     * Overrides DboSource::renderStatement to handle schema generation with Postgres-style indexes.
     *
     * @param string $type
     * @param array  $data
     *
     * @return string
     */
    public function renderStatement($type, $data)
    {
        switch (strtolower($type)) {
            case 'schema':
                extract($data);

                foreach ($indexes as $i => $index) {
                    if (preg_match('/PRIMARY KEY/', $index)) {
                        unset($indexes[$i]);
                        $columns[] = $index;
                        break;
                    }
                }
                $join = ['columns' => ",\n\t", 'indexes' => "\n"];

                foreach (['columns', 'indexes'] as $var) {
                    if (is_array(${$var})) {
                        ${$var} = implode($join[$var], array_filter(${$var}));
                    }
                }

                return "CREATE TABLE {$table} (\n\t{$columns}\n);\n{$indexes}";
            break;
            default:
                return parent::renderStatement($type, $data);
            break;
        }
    }

    /**
     * Helper function to clean the incoming values.
     **/
    public function escape($str)
    {
        if ($str == '') {
            return '';
        }

        if (function_exists('db2_escape_string')) {
            $str = db2_escape_string($str);
        } else {
            $str = addslashes($str);
        }

        return trim($str);
    }
}
