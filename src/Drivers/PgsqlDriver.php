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
class PgsqlDriver extends DboSource
{
    /**
     * Starting Quote.
     *
     * @var string
     */
    protected $startQuote = '"';

    /**
     * Ending Quote.
     *
     * @var string
     */
    protected $endQuote = '"';

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
        'port'       => 5432,
        'charset'    => '',
    ];

    /**
     * Index of basic SQL commands.
     *
     * @var array
     */
    protected $commands = [
        'begin'    => 'BEGIN',
        'commit'   => 'COMMIT',
        'rollback' => 'ROLLBACK',
    ];

    /**
     * {@inheritdoc}
     */
    public function enabled()
    {
        return extension_loaded('pgsql');
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $config = $this->config;
        $config = array_merge($this->baseConfig, $config);

        $conn = "host='{$config['host']}' port='{$config['port']}' dbname='{$config['database']}' ";
        $conn .= "user='{$config['username']}' password='{$config['password']}'";

        if (!$config['persistent']) {
            $this->connection = pg_connect($conn, PGSQL_CONNECT_FORCE_NEW);
        } else {
            $this->connection = pg_pconnect($conn);
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
            pg_free_result($this->result);
        }
        if (is_resource($this->connection)) {
            $this->connected = !pg_close($this->connection);
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
        return pg_query($this->connection, $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($sql)
    {
        $this->result = $this->query($sql);

        $data = pg_fetch_all($this->result);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function lastError()
    {
        $error = pg_last_error($this->connection);

        return ($error) ? $error : null;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return @pg_last_oid($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function lastAffected()
    {
        return ($this->result) ? pg_affected_rows($this->result) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function lastNumRows()
    {
        return ($this->result) ? pg_num_rows($this->result) : false;
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
     * {@inheritdoc}
     */
    public function escape($str)
    {
        if ($str == '') {
            return '';
        }

        if (function_exists('pg_escape_string')) {
            $str = pg_escape_string($str);
        } else {
            $str = addslashes($str);
        }

        return trim($str);
    }
}
