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
    protected $startQuote = '`';
    protected $endQuote   = '`';
    protected $attempts   = 0;
    protected $baseConfig = [
        'persistent' => false,
        'host'       => 'localhost',
        'username'   => 'root',
        'password'   => '',
        'database'   => 'logics',
        'port'       => '3306',
        'charset'    => 'UTF8',
        'timezone'   => '',
    ];

    /**
     * {@inheritdoc}
     */
    public function enabled()
    {
        return extension_loaded('mysqli');
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $config = $this->config;

        $config          = @array_merge($this->baseConfig, $config);
        $this->connected = false;

        if (is_numeric($config['port'])) {
            $config['socket'] = null;
        } else {
            $config['socket'] = $config['port'];
            $config['port']   = null;
        }

        $this->connection = mysqli_connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);

        if ($this->connection !== false) {
            $this->connected = true;
            $this->attempts  = 0;
        }

        if (!empty($config['charset'])) {
            $this->setCharset($config['charset']);
        }

        if (!empty($config['timezone'])) {
            $this->setTimezone($config['timezone']);
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function disConnect()
    {
        if (isset($this->results) && is_resource($this->results)) {
            mysqli_free_result($this->results);
        }
        $this->connected = !@mysqli_close($this->connection);

        return !$this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        $result = mysqli_query($this->connection, $sql);
        if (!$result) {
            $messages = [
                'MySQL server has gone away',
                'php_network_getaddresses: getaddrinfo failed:',
            ];

            $connect = false;
            $error   = $this->lastError();

            foreach ($messages as $message) {
                if (strpos($error, $message) !== false) {
                    $connect = true;
                }
            }

            if ($connect && $this->attempts <= 3) {
                ++$this->attempts;
                $this->disconnect();
                $this->connect();

                return $this->query($sql);
            }

            $this->logSqlError($sql);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($sql)
    {
        $this->result = $this->query($sql);

        if (!$this->result) {
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($this->result)) {
            $rows[] = $row;
        }

        mysqli_free_result($this->result);

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function lastError()
    {
        if ($this->connection && mysqli_errno($this->connection)) {
            return mysqli_errno($this->connection).': '.mysqli_error($this->connection);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return mysqli_insert_id($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function lastAffected()
    {
        if ($this->result) {
            return mysqli_affected_rows($this->connection);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function lastNumRows()
    {
        if ($this->result) {
            return mysqli_num_rows($this->result);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    private function setCharset($enc)
    {
        return $this->query('SET NAMES '.$enc) != false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return mysqli_client_encoding($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    private function setTimezone($zone)
    {
        return $this->query('SET time_zone = '.$zone) != false;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($str)
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
