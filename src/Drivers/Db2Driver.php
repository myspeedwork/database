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
    protected $baseConfig = [
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
     * {@inheritdoc}
     */
    public function enabled()
    {
        return extension_loaded('ibm_db2');
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $config = $this->config;
        $config = array_merge($this->baseConfig, $config);

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
            $this->setCharset($config['charset']);
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function disConnect()
    {
        if ($this->result) {
            db2_free_result($this->result);
        }
        if (is_resource($this->connection)) {
            $this->connected = !db2_close($this->connection);
        } else {
            $this->connected = false;
        }

        return !$this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        $stmt = db2_prepare($this->connection, $sql);
        $res  = db2_execute($stmt);

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($sql)
    {
        $data         = [];
        $stmt         = db2_prepare($this->connection, $sql);
        $this->result = db2_execute($stmt);

        while ($row = db2_fetch_assoc($stmt)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function lastError()
    {
        $error = db2_conn_errormsg($this->connection);

        return ($error) ? $error : null;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return @db2_last_lastInsertId($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function lastAffected()
    {
        return ($this->result) ? db2_num_rows($this->result) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function lastNumRows()
    {
        return ($this->result) ? db2_num_rows($this->result) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function setCharset($enc)
    {
        return $this->query('SET NAMES '.$enc) != false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return pg_client_encoding($this->connection);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function renderStatement($type, $data)
    {
        switch (strtolower($type)) {
            case 'schema':
                extract($data);

                $indexes = $data['indexes'];
                $columns = $data['columns'];

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

                return 'CREATE TABLE '.$data['table']." (\n\t".$columns."\n);\n".$indexes;
            break;
            default:
                return parent::renderStatement($type, $data);
            break;
        }
    }

    /**
     * {@inheritdoc}
     */
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
