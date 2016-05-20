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
class SqliteDriver extends DboSource
{
    protected $startQuote = '"';
    protected $endQuote   = '"';

    /**
     * Keeps the transaction statistics of CREATE/UPDATE/DELETE queries.
     *
     * @var array
     */
    protected $_queryStats = [];

    /**
     * {@inheritdoc}
     */
    protected $baseConfig = [
        'persistent' => true,
        'database'   => null,
    ];

    /**
     * {@inheritdoc}
     */
    protected $commands = [
        'begin'    => 'BEGIN TRANSACTION',
        'commit'   => 'COMMIT TRANSACTION',
        'rollback' => 'ROLLBACK TRANSACTION',
    ];

    /**
     * {@inheritdoc}
     */
    public function enabled()
    {
        return extension_loaded('sqlite');
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $config = $this->config;
        $config = array_merge($this->baseConfig, $config);
        if (!$config['persistent']) {
            $this->connection = sqlite_open($config['database']);
        } else {
            $this->connection = sqlite_popen($config['database']);
        }
        $this->connected = is_resource($this->connection);

        if ($this->connected) {
            $this->query('PRAGMA count_changes = 1;');
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
        $this->connected = !@sqlite_close($this->connection);

        return !$this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        if (preg_match('/^(INSERT|UPDATE|DELETE)/', $sql)) {
            list($this->_queryStats) = $this->fetch($sql);

            return $this->result;
        }
        $this->result = sqlite_query($this->connection, $sql);

        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($sql)
    {
        $data         = [];
        $this->result = sqlite_query($this->connection, $sql);

        while ($row = @sqlite_fetch_array($this->result, SQLITE_ASSOC)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function lastError()
    {
        $error = sqlite_last_error($this->connection);
        if ($error) {
            return $error.': '.sqlite_error_string($error);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId()
    {
        return sqlite_last_insert_rowid($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function lastAffected()
    {
        if (!empty($this->_queryStats)) {
            foreach (['rows inserted', 'rows updated', 'rows deleted'] as $key) {
                if (array_key_exists($key, $this->_queryStats)) {
                    return $this->_queryStats[$key];
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function lastNumRows()
    {
        if ($this->hasResult()) {
            sqlite_num_rows($this->result);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function limit($limit, $offset = null)
    {
        if ($limit) {
            $rt = '';
            if (!strpos(strtolower($limit), 'limit') || strpos(strtolower($limit), 'limit') === 0) {
                $rt = ' LIMIT';
            }
            $rt .= ' '.$limit;
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
    public function setCharset($enc)
    {
        if (!in_array($enc, ['UTF-8', 'UTF-16', 'UTF-16le', 'UTF-16be'])) {
            return false;
        }

        return $this->query("PRAGMA encoding = \"{$enc}\"") !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return $this->fetchRow('PRAGMA encoding');
    }

    /**
     * {@inheritdoc}
     */
    public function renderStatement($type, $data)
    {
        switch (strtolower($type)) {
            case 'schema':
                foreach (['columns', 'indexes'] as $var) {
                    if (is_array($data[$var])) {
                        $data[$var] = "\t".implode(",\n\t", array_filter($data[$var]));
                    }
                }

                return 'CREATE TABLE '.$data['table']." (\n".$data['columns'].");\n{".$data['indexes'].'}';
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

        if (function_exists('sqlite_escape_string')) {
            $str = sqlite_escape_string($str);
        } else {
            $str = addslashes($str);
        }

        return trim($str);
    }
}
