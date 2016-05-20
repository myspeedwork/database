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
class MssqlDriver extends DboSource
{
    private $lastError    = false;
    protected $startQuote = '[';
    protected $endQuote   = ']';
    protected $baseConfig = [
        'persistent' => true,
        'host'       => 'localhost',
        'username'   => 'root',
        'password'   => '',
        'database'   => 'logics',
        'port'       => '1433',
    ];
    protected $commands = [
        'begin'    => 'BEGIN TRANSACTION',
        'commit'   => 'COMMIT',
        'rollback' => 'ROLLBACK',
    ];

    /**
     * {@inheritdoc}
     */
    public function enabled()
    {
        return extension_loaded('mssql');
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $config = $this->config;
        $config = array_merge($this->baseConfig, $config);

        $os = env('OS');
        if (!empty($os) && strpos($os, 'Windows') !== false) {
            $sep = ',';
        } else {
            $sep = ':';
        }
        $this->connected = false;

        if (is_numeric($config['port'])) {
            $port = $sep.$config['port'];    // Port number
        } elseif ($config['port'] === null) {
            $port = '';                        // No port - SQL Server 2005
        } else {
            $port = '\\'.$config['port'];    // Named pipe
        }

        if (!$config['persistent']) {
            $this->connection = mssql_connect($config['host'].$port, $config['username'], $config['password'], true);
        } else {
            $this->connection = mssql_pconnect($config['host'].$port, $config['username'], $config['password']);
        }

        if (mssql_select_db($config['database'], $this->connection)) {
            $this->qery('SET DATEFORMAT ymd');
            $this->connected = true;
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function disConnect()
    {
        @mssql_free_result($this->results);
        $this->connected = !@mssql_close($this->connection);

        return !$this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        $result          = @mssql_query($sql, $this->connection);
        $this->lastError = ($result === false);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($sql)
    {
        $this->result = $this->query($sql);

        $data = [];
        while ($row = @mssql_fetch_array($this->result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function lastError()
    {
        if ($this->lastError) {
            $error = mssql_get_last_message();
            if ($error && !preg_match('/contexto de la base de datos a|contesto di database|changed database|contexte de la base de don|datenbankkontext/i', $error)) {
                return $error;
            }
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return mssql_result(mysql_query('select SCOPE_IDENTITY()', $this->connection), 0, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function lastAffected()
    {
        if ($this->result) {
            return mssql_rows_affected($this->connection);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function lastNumRows()
    {
        if ($this->result) {
            return mssql_num_rows($this->result);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function limit($limit, $offset = null)
    {
        if ($limit) {
            $rt = '';
            if (!strpos(strtolower($limit), 'top') || strpos(strtolower($limit), 'top') === 0) {
                $rt = ' TOP';
            }
            $rt .= ' '.$limit;
            if (is_int($offset) && $offset > 0) {
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
            case 'select':
                extract($data);
                $fields = trim($fields);

                if (strpos($limit, 'TOP') !== false && strpos($fields, 'DISTINCT ') === 0) {
                    $limit  = 'DISTINCT '.trim($limit);
                    $fields = substr($fields, 9);
                }

                if (preg_match('/offset\s+([0-9]+)/i', $limit, $offset)) {
                    $limit = preg_replace('/\s*offset.*$/i', '', $limit);
                    preg_match('/top\s+([0-9]+)/i', $limit, $limitVal);
                    $offset                = intval($offset[1]) + intval($limitVal[1]);
                    $rOrder                = $this->switchSort($order);
                    list($order2, $rOrder) = [$this->mapFields($order), $this->mapFields($rOrder)];

                    return "SELECT * FROM (SELECT {$limit} * FROM (SELECT TOP {$offset} {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order}) AS Set1 {$rOrder}) AS Set2 {$order2}";
                } else {
                    return "SELECT {$limit} {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order}";
                }
                break;
            case 'schema':
                extract($data);

                $indexes = $data['indexes'];
                $columns = $data['columns'];

                foreach ($indexes as $i => $index) {
                    if (preg_match('/PRIMARY KEY/', $index)) {
                        unset($indexes[$i]);
                        break;
                    }
                }

                foreach (['columns', 'indexes'] as $var) {
                    if (is_array(${$var})) {
                        ${$var} = "\t".implode(",\n\t", array_filter(${$var}));
                    }
                }

                return 'CREATE TABLE '.$data['table']." (\n".$columns.");\n".$indexes;
            break;
            default:
                return parent::renderStatement($type, $data);
            break;
        }
    }

    /**
     * Reverses the sort direction of ORDER statements to get paging offsets to work correctly.
     *
     * @param string $order
     *
     * @return string
     */
    private function switchSort($order)
    {
        $order = preg_replace('/\s+ASC/i', '__tmp_asc__', $order);
        $order = preg_replace('/\s+DESC/i', ' ASC', $order);

        return preg_replace('/__tmp_asc__/', ' DESC', $order);
    }

    /**
     * Translates field names used for filtering and sorting to shortened names using the field map.
     *
     * @param string $sql A snippet of SQL representing an ORDER or WHERE statement
     *
     * @return string The value of $sql with field names replaced
     */
    private function mapFields($sql)
    {
        if (empty($sql) || empty($this->__fieldMappings)) {
            return $sql;
        }
        foreach ($this->__fieldMappings as $key => $val) {
            $sql = preg_replace('/'.preg_quote($val).'/', $this->name($key), $sql);
            $sql = preg_replace('/'.preg_quote($this->name($val)).'/', $this->name($key), $sql);
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($str)
    {
        if ($str == '') {
            return '';
        }

        if (function_exists('mssql_real_escape_string')) {
            $str = mssql_real_escape_string($str);
        } else {
            $str = addslashes($str);
        }

        return trim($str);
    }
}
