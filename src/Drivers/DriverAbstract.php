<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database\Drivers;

use Speedwork\Container\Container;
use Speedwork\Util\Str;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
abstract class DriverAbstract
{
    protected $container;
    /**
     * Start quote.
     *
     * @var string
     */
    protected $startQuote = '`';

    /**
     * End quote.
     *
     * @var string
     */
    protected $endQuote = '`';

    /**
     * index definition, primary, index, unique.
     *
     * @var array
     */
    protected $index = ['PRI' => 'primary', 'MUL' => 'index', 'UNI' => 'unique'];

    /**
     * Database keyword used to assign aliases to identifiers.
     *
     * @var string
     */
    protected $alias = 'AS ';

    /**
     * The set of valid SQL operations usable in a WHERE statement.
     *
     * @var array
     */
    protected $sqlOps = ['like', 'ilike', 'or', 'not', 'in', 'between', 'regexp', 'similar to'];

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

    protected $connection = false;

    /**
     * Are we connected to the DataSource?
     *
     * @var bool
     */
    protected $connected = false;

    /**
     * The default configuration of a specific DataSource.
     *
     * @var array
     */
    protected $baseConfig = [];

    /**
     * The DataSource configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Whether or not this DataSource is in the middle of a transaction.
     *
     * @var bool
     */
    protected $transaction = false;

    /**
     * Result.
     *
     * @var array
     */
    protected $result = null;

    /**
     * Check whether the conditions for the Datasource being available
     * are satisfied. Often used from connect() to check for support
     * before establishing a connection.
     *
     * @return bool Whether or not the Datasources conditions for use are met
     */
    public function enabled()
    {
        return true;
    }

    /**
     * Connects to the database using options in the given configuration array.
     *
     * @return bool True if the database could be connected, else false
     */
    abstract public function connect();

    /**
     * Disconnects from database.
     *
     * @return bool True if the database could be disconnected, else false
     */
    abstract public function disConnect();

    /**
     * Executes given SQL statement.
     *
     * @param string $sql SQL statement
     *
     * @return resource Result resource identifier
     */
    abstract public function fetch($sql);

    /**
     * Returns a formatted error message from previous database operation.
     *
     * @return string Error message with error number
     */
    abstract public function lastError();

    /**
     * Executes given SQL statement.
     *
     * @param string $sql SQL statement
     *
     * @return resource Result resource identifier
     */
    abstract public function query($sql);

    /**
     * Returns the ID generated from the previous INSERT operation.
     *
     * @param mixed $source
     *
     * @return mixed Last ID key generated in previous INSERT
     */
    abstract public function lastInsertId();

    /**
     * Returns the number of rows affected by last query.
     *
     * @param mixed $source
     *
     * @return int Number of rows affected by last query
     */
    abstract public function lastAffected();

    /**
     * Returns the number of rows returned by last operation.
     *
     * @param mixed $source
     *
     * @return int Number of rows returned by last operation
     */
    abstract public function lastNumRows();

    /**
     * Helper function to clean the incoming values.
     *
     * @return string Formated message
     **/
    abstract public function escape($str);

    /**
     * Sets the configuration for the DataSource.
     * Merges the $config information with the baseConfig and the existing $config property.
     *
     * @param array $config The configuration array
     */
    public function setConfig($config = [])
    {
        $this->config = array_merge($this->baseConfig, $this->config, $config);
    }

    /**
     * Get the configuration for the DataSource.
     *
     * @param array $config The configuration array
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Builds and generates an SQL statement from an array.  Handles final clean-up before conversion.
     *
     * @param array  $query An array defining an SQL query
     * @param object $model The model object which initiated the query
     *
     * @return string An executable SQL statement
     **/
    public function buildStatement(&$query, $table = '', $type = 'select')
    {
        $table = ($query['table']) ? $query['table'] : $table;

        $query = @array_merge(['offset' => null, 'joins' => []], $query);
        if (!empty($query['joins'])) {
            $count = count($query['joins']);
            for ($i = 0; $i < $count; ++$i) {
                if (is_array($query['joins'][$i])) {
                    $query['joins'][$i] = $this->buildJoinStatement($query['joins'][$i]);
                }
            }
        }

        return $this->renderStatement(
            $type, [
            'conditions' => $this->conditions($query['conditions'], true, true),
            'fields'     => (count($query['fields']) > 0) ? @implode(', ', $query['fields']) : ' * ',
            'values'     => (count($query['values']) > 0) ? @implode(', ', $query['values']) : '',
            'table'      => $table,
            'alias'      => ($query['alias']) ? $this->alias.$this->name($query['alias']) : '',
            'order'      => $this->order($query['order']),
            'limit'      => $this->limit($query['limit'], $query['offset'], $query['page']),
            'joins'      => @implode(' ', $query['joins']),
            'group'      => $this->group($query['group']),
            ]
        );
    }

    /**
     * Alias function of buildStatement.
     **/
    public function buildQuery($query, $table = '', $type = 'select')
    {
        return $this->buildStatement($query, $table, $type);
    }

    /**
     * Renders a final SQL JOIN statement.
     *
     * @param array $data
     *
     * @return string
     */
    public function renderJoinStatement($data)
    {
        return trim($data['type'].' JOIN '.$data['table'].' '.$data['alias'].' ON ('.$data['conditions'].')');
    }

    /**
     * Builds and generates a JOIN statement from an array.  Handles final clean-up before conversion.
     *
     * @param array $join An array defining a JOIN statement in a query
     *
     * @return string An SQL JOIN statement to be used in a query
     */
    public function buildJoinStatement($join)
    {
        $data = array_merge(
            [
            'type'       => null,
            'alias'      => null,
            'table'      => 'join_table',
            'conditions' => [],
            ], $join
        );

        if (!empty($data['alias'])) {
            $data['alias'] = $this->alias.$this->name($data['alias']);
        }
        if (!empty($data['conditions'])) {
            $data['conditions'] = trim($this->conditions($data['conditions'], true, false));
        }

        return $this->renderJoinStatement($data);
    }

    /**
     * Renders a final SQL statement by putting together the component parts in the correct order.
     *
     * @param string $type
     * @param array  $data
     *
     * @return string
     */
    public function renderStatement($type, $data)
    {
        $aliases = null;
        $alias   = $data['alias'];

        switch (strtolower($type)) {
            case 'select':
                return 'SELECT '.$data['fields'].' FROM '.$data['table'].' '.$data['alias'].' '.$data['joins'].' '.$data['conditions'].' '.$data['group'].' '.$data['order'].' '.$data['limit'].'';
                break;
            case 'create':
            case 'insert':
                $values = $data['values'];
                $values = rtrim($values, ')');
                $values = ltrim($values, '(');

                return 'INSERT INTO '.$data['table'].' ('.$data['fields'].') VALUES ('.$values.')';
                break;
            case 'update':
                if (!empty($alias)) {
                    $aliases = "{$this->alias}".$data['alias'].' '.$data['joins'].' ';
                }

                return 'UPDATE '.$data['table'].' '.$aliases.'SET '.$data['fields'].' '.$data['conditions'].'';
                break;
            case 'delete':
                if (!empty($alias)) {
                    $aliases = "{$this->alias}".$data['alias'].' '.$data['joins'].' ';
                }

                return 'DELETE '.$data['alias'].' FROM '.$data['table'].' '.$aliases.' '.$data['conditions'].' '.$data['limit'].'';
                break;
            case 'schema':
                foreach (['columns', 'indexes'] as $var) {
                    if (is_array($data[$var])) {
                        $data[$var] = "\t".implode(",\n\t", array_filter($data[$var]));
                    }
                }

                $indexes = $data['indexes'];
                $columns = $data['columns'];
                if (trim($indexes) != '') {
                    $columns .= ',';
                }

                return 'CREATE TABLE '.$data['table']." (\n".$data['columns'].''.$data['indexes'].');';
                break;
        }
    }

    /**
     * Creates a WHERE clause by parsing given conditions data.  If an array or string
     * conditions are provided those conditions will be parsed and quoted.  If a boolean
     * is given it will be integer cast as condition.  Null will return 1 = 1.
     *
     * @param mixed $conditions  Array or string of conditions, or any value
     * @param bool  $quoteValues If true, values should be quoted
     * @param bool  $where       If true, "WHERE " will be prepended to the return value
     * @param Model $model       A reference to the Model instance making the query
     *
     * @return string SQL fragment
     */
    public function conditions($conditions, $quoteValues = true, $where = true, $model = null)
    {
        $clause = $out = '';

        if ($where) {
            $clause = ' WHERE ';
        }

        if (is_array($conditions) && !empty($conditions)) {
            $out = $this->conditionKeysToString($conditions, $quoteValues, $model);

            if (empty($out)) {
                return $clause.' 1 = 1';
            }

            return $clause.implode(' AND ', $out);
        }
        if ($conditions === false || $conditions === true) {
            return $clause.(int) $conditions.' = 1';
        }

        if (empty($conditions) || trim($conditions) == '') {
            return $clause.'1 = 1';
        }
        $clauses = '/^WHERE\\x20|^GROUP\\x20BY\\x20|^HAVING\\x20|^ORDER\\x20BY\\x20/i';

        if (preg_match($clauses, $conditions)) {
            $clause = '';
        }
        if (trim($conditions) == '') {
            $conditions = ' 1 = 1';
        } else {
            $conditions = $this->quoteFields($conditions);
        }

        return $clause.$conditions;
    }

    /**
     * Creates a WHERE clause by parsing given conditions array.  Used by DboSource::conditions().
     *
     * @param array $conditions  Array or string of conditions
     * @param bool  $quoteValues If true, values should be quoted
     * @param Model $model       A reference to the Model instance making the query
     *
     * @return string SQL fragment
     */
    public function conditionKeysToString($conditions, $quoteValues = true)
    {
        $out  = [];
        $data = null;
        $bool = ['and', 'or', 'not', 'and not', 'or not', 'xor', '||', '&&'];

        foreach ($conditions as $key => $value) {
            $join = ' AND ';
            $not  = null;

            if (is_array($value)) {
                $valueInsert = (
                    !empty($value) &&
                    (substr_count($key, '?') == count($value) || substr_count($key, ':') == count($value))
                );
            }

            if (is_numeric($key) && empty($value)) {
                continue;
            } elseif (is_numeric($key) && is_string($value)) {
                $out[] = $not.$this->quoteFields($value);
            } elseif ((is_numeric($key) && is_array($value)) || in_array(strtolower(trim($key)), $bool)) {
                if (in_array(strtolower(trim($key)), $bool)) {
                    $join = ' '.strtoupper($key).' ';
                } else {
                    $key = $join;
                }
                $value = $this->conditionKeysToString($value, $quoteValues);

                if (strpos($join, 'NOT') !== false) {
                    if (strtoupper(trim($key)) == 'NOT') {
                        $key = 'AND '.trim($key);
                    }
                    $not = 'NOT ';
                }

                if (empty($value[1])) {
                    if ($not) {
                        $out[] = $not.'('.$value[0].')';
                    } else {
                        $out[] = $value[0];
                    }
                } else {
                    $out[] = '('.$not.'('.implode(') '.strtoupper($key).' (', $value).'))';
                }
            } else {
                if (is_object($value) && isset($value->type)) {
                    if ($value->type == 'identifier') {
                        $data .= $this->name($key).' = '.$this->name($value->value);
                    } elseif ($value->type == 'expression') {
                        if (is_numeric($key)) {
                            $data .= $value->value;
                        } else {
                            $data .= $this->name($key).' = '.$value->value;
                        }
                    }
                } elseif (is_array($value) && !empty($value) && !$valueInsert) {
                    if (array_keys($value) === array_values(array_keys($value))) {
                        $data = $this->quoteFields($key).' IN (';
                        if ($quoteValues || strpos($value[0], '-!') !== 0) {
                            $value = $this->value($value);
                            if (!empty($value)) {
                                $data .= implode(', ', $value);
                            } else {
                                $data = "''";
                            }
                        }
                        $data .= ')';
                    } else {
                        $ret = $this->conditionKeysToString($value, $quoteValues);
                        if (count($ret) > 1) {
                            $data = '('.implode(') AND (', $ret).')';
                        } elseif (isset($ret[0])) {
                            $data = $ret[0];
                        }
                    }
                } elseif (is_numeric($key) && !empty($value)) {
                    $data = $this->quoteFields($value);
                } else {
                    $data = $this->parseKey(trim($key), $value);
                }

                if ($data !== null) {
                    if (preg_match('/^\(\(\((.+)\)\)\)$/', $data)) {
                        $data = substr($data, 1, strlen($data) - 2);
                    }
                    $out[] = $data;
                    $data  = null;
                }
            }
        }

        return $out;
    }

    /**
     * Extracts a Model.field identifier and an SQL condition operator from a string, formats
     * and inserts values, and composes them into an SQL snippet.
     *
     * @param Model  $model Model object initiating the query
     * @param string $key   An SQL key snippet containing a field and optional SQL operator
     * @param mixed  $value The value(s) to be inserted in the string
     *
     * @return string
     */
    protected function parseKey($key, $value)
    {
        $operatorMatch = '/^(('.implode(')|(', $this->sqlOps);
        $operatorMatch .= '\\x20)|<[>=]?(?![^>]+>)\\x20?|[>=!]{1,3}(?!<)\\x20?)/is';
        $bound = (strpos($key, '?') !== false || (is_array($value) && strpos($key, ':') !== false));

        if (!strpos($key, ' ')) {
            $operator = '=';
        } else {
            list($key, $operator) = explode(' ', trim($key), 2);

            if (!preg_match($operatorMatch, trim($operator)) && strpos($operator, ' ') !== false) {
                $key      = $key.' '.$operator;
                $split    = strrpos($key, ' ');
                $operator = substr($key, $split);
                $key      = substr($key, 0, $split);
            }
        }

        $null = ($value === null || (is_array($value) && empty($value)));

        if (strtolower($operator) === 'not') {
            $data = $this->conditionKeysToString(
                [$operator => [$key => $value]], true
            );

            return $data[0];
        }

        $value = $this->value($value);

        if ($key !== '?') {
            $isKey = (strpos($key, '(') !== false || strpos($key, ')') !== false);
            $key   = $isKey ? $this->quoteFields($key) : $this->name($key);
        }

        if ($bound) {
            return Str::insert($key.' '.trim($operator), $value);
        }

        if (!preg_match($operatorMatch, trim($operator))) {
            $operator .= ' =';
        }
        $operator = trim($operator);

        if (is_array($value)) {
            $value = implode(', ', $value);

            switch ($operator) {
                case '=':
                    $operator = 'IN';
                    break;
                case '!=':
                case '<>':
                    $operator = 'NOT IN';
                    break;
            }

            if (!empty($value)) {
                $value = "({$value})";
            } else {
                $value = "('')";
            }
        } elseif ($null || $value === 'NULL') {
            switch ($operator) {
                case '=':
                    $operator = 'IS';
                    break;
                case '!=':
                case '<>':
                    $operator = 'IS NOT';
                    break;
            }
            $value = 'NULL';
        }

        return "{$key} {$operator} {$value}";
    }

    /**
     * Quotes Model.fields.
     *
     * @param string $conditions
     *
     * @return string or false if no match
     */
    protected function quoteFields($conditions)
    {
        $start    = $end    = null;
        $original = $conditions;

        if (!empty($this->startQuote)) {
            $start = preg_quote($this->startQuote);
        }
        if (!empty($this->endQuote)) {
            $end = preg_quote($this->endQuote);
        }
        $conditions = str_replace([$start, $end], '', $conditions);
        preg_match_all('/(?:[\'\"][^\'\"\\\]*(?:\\\.[^\'\"\\\]*)*[\'\"])|([a-z0-9_'.$start.$end.']*\\.[a-z0-9_'.$start.$end.']*)/i', $conditions, $replace, PREG_PATTERN_ORDER);

        if (isset($replace['1']['0'])) {
            $pregCount = count($replace['1']);

            for ($i = 0; $i < $pregCount; ++$i) {
                if (!empty($replace['1'][$i]) && !is_numeric($replace['1'][$i])) {
                    $conditions = preg_replace('/\b'.preg_quote($replace['1'][$i]).'\b/', $this->name($replace['1'][$i]), $conditions);
                }
            }

            return $conditions;
        }

        return $original;
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
            if (!stripos($limit, 'limit') || strpos(strtolower($limit), 'limit') === 0) {
                $rt = ' LIMIT';
            }

            if (intval($offset)) {
                $rt .= ' '.$offset.',';
            }

            if (intval($page) && !$offset) {
                $rt .= ' '.$limit * ($page - 1).',';
            }

            $rt .= ' '.$limit;

            return $rt;
        }
    }

    /**
     * Returns an ORDER BY clause as a string.
     *
     * @param string $key       Field reference, as a key (i.e. Post.title)
     * @param string $direction Direction (ASC or DESC)
     *
     * @return string ORDER BY clause
     */
    protected function order($keys, $direction = 'ASC')
    {
        if (is_string($keys) && strpos($keys, ',') && !preg_match('/\(.+\,.+\)/', $keys)) {
            $keys = array_map('trim', explode(',', $keys));
        }

        if (is_array($keys)) {
            $keys = array_filter($keys);
        }

        if (empty($keys) || (is_array($keys) && isset($keys[0]) && empty($keys[0]))) {
            return '';
        }

        if (is_array($keys)) {
            $keys = ($this->countDim($keys) > 1) ? array_map([&$this, 'order'], $keys) : $keys;

            foreach ($keys as $key => $value) {
                if (is_numeric($key)) {
                    $key   = $value   = ltrim(str_replace('ORDER BY ', '', $this->order($value)));
                    $value = (!preg_match('/\\x20ASC|\\x20DESC/i', $key) ? ' '.$direction : '');
                } else {
                    $value = ' '.$value;
                }

                if (!preg_match('/^.+\\(.*\\)/', $key) && !strpos($key, ',')) {
                    if (preg_match('/\\x20ASC|\\x20DESC/i', $key, $dir)) {
                        $dir = $dir[0];
                        $key = preg_replace('/\\x20ASC|\\x20DESC/i', '', $key);
                    } else {
                        $dir = '';
                    }
                    $key = trim($key);
                    if (!preg_match('/\s/', $key)) {
                        $key = $this->name($key);
                    }
                    $key .= ' '.trim($dir);
                }
                $order[] = $this->order($key.$value);
            }

            return ' ORDER BY '.trim(str_replace('ORDER BY', '', implode(',', $order)));
        }
        $keys = preg_replace('/ORDER\\x20BY/i', '', $keys);

        if (strpos($keys, '.')) {
            preg_match_all('/([a-zA-Z0-9_]{1,})\\.([a-zA-Z0-9_]{1,})/', $keys, $result, PREG_PATTERN_ORDER);
            $pregCount = count($result[0]);

            for ($i = 0; $i < $pregCount; ++$i) {
                if (!is_numeric($result[0][$i])) {
                    $keys = preg_replace('/'.$result[0][$i].'/', $this->name($result[0][$i]), $keys);
                }
            }
            $result = ' ORDER BY '.$keys;

            return $result.(!preg_match('/\\x20ASC|\\x20DESC/i', $keys) ? ' '.$direction : '');
        } elseif (preg_match('/(\\x20ASC|\\x20DESC)/i', $keys, $match)) {
            $direction = $match[1];

            return ' ORDER BY '.preg_replace('/'.$match[1].'/', '', $keys).$direction;
        }

        return ' ORDER BY '.$keys.' '.$direction;
    }

    /**
     * Create a GROUP BY SQL clause.
     *
     * @param string $group Group By Condition
     *
     * @return mixed string condition or null
     */
    protected function group($group)
    {
        if ($group) {
            if (is_array($group)) {
                $group = implode(', ', $group);
            }

            return ' GROUP BY '.$this->quoteFields($group);
        }
    }

    /**
     * Prepares a value, or an array of values for database queries by quoting and escaping them.
     *
     * @param mixed  $name   A value or an array of values to prepare
     * @param string $column The column into which this data will be inserted
     * @param bool   $read   Value to be used in READ or WRITE context
     *
     * @return mixed Prepared value or array of values
     */
    public function value($value)
    {
        if (is_array($value) && !empty($value)) {
            return array_map([&$this, 'value'], $value);
        }

        if (is_object($value) && isset($value->type)) {
            if ($value->type == 'identifier') {
                return $this->name($value->value);
            } elseif ($value->type == 'expression') {
                return $value->value;
            }
        }

        if (stripos($value, 'sql:select') !== false) {
            return str_replace(['sql:'], '', $value);
        }

        if (null === $value || 'NULL' === $value) {
            return 'NULL';
        }

        if (!isset($value) || is_bool($value)) {
            return "''";
        }

        if (is_string($value)) {
            return "'".$this->escape($value)."'";
        }

        return $value;
    }

    /**
     * Returns a quoted name of $data for use in an SQL statement.
     * Strips fields out of SQL functions before quoting.
     *
     * @param string $data
     *
     * @return string SQL field
     */
    public function name($data)
    {
        if ($data == '*') {
            return '*';
        }
        if (is_object($data) && isset($data->type)) {
            return $data->value;
        }
        $array = is_array($data);
        $data  = (array) $data;
        $count = count($data);

        for ($i = 0; $i < $count; ++$i) {
            if ($data[$i] == '*') {
                continue;
            }
            if (strpos($data[$i], '(') !== false && preg_match_all('/([^(]*)\((.*)\)(.*)/', $data[$i], $fields)) {
                $fe = [];
                foreach ($fields as $field) {
                    $fe[] = $field[0];
                }

                $fields = $fe;

                if (!empty($fields[1])) {
                    if (!empty($fields[2])) {
                        $data[$i] = $fields[1].'('.$this->name($fields[2]).')'.$fields[3];
                    } else {
                        $data[$i] = $fields[1].'()'.$fields[3];
                    }
                }
            }
            $data[$i] = str_replace('.', $this->endQuote.'.'.$this->startQuote, $data[$i]);
            $data[$i] = $this->startQuote.$data[$i].$this->endQuote;
            $data[$i] = str_replace($this->startQuote.$this->startQuote, $this->startQuote, $data[$i]);
            $data[$i] = str_replace($this->startQuote.'(', '(', $data[$i]);
            $data[$i] = str_replace(')'.$this->startQuote, ')', $data[$i]);
            $alias    = !empty($this->alias) ? $this->alias : 'AS ';

            if (preg_match('/\s+'.$alias.'\s*/', $data[$i])) {
                if (preg_match('/\w+\s+'.$alias.'\s*/', $data[$i])) {
                    $quoted   = $this->endQuote.' '.$alias.$this->startQuote;
                    $data[$i] = str_replace(' '.$alias, $quoted, $data[$i]);
                } else {
                    $quoted   = $alias.$this->startQuote;
                    $data[$i] = str_replace($alias, $quoted, $data[$i]).$this->endQuote;
                }
            }

            if (!empty($this->endQuote) && $this->endQuote == $this->startQuote) {
                if (substr_count($data[$i], $this->endQuote) % 2 == 1) {
                    if (substr($data[$i], -2) == $this->endQuote.$this->endQuote) {
                        $data[$i] = substr($data[$i], 0, -1);
                    } else {
                        $data[$i] = trim($data[$i], $this->endQuote);
                    }
                }
            }
            if (strpos($data[$i], '*')) {
                $data[$i] = str_replace($this->endQuote.'*'.$this->endQuote, '*', $data[$i]);
            }
            $data[$i] = str_replace($this->endQuote.$this->endQuote, $this->endQuote, $data[$i]);
        }

        return (!$array) ? $data[0] : $data;
    }

    /**
     * Returns a quoted name of $data for use in an SQL statement.
     * Strips fields out of SQL functions before quoting.
     *
     * Results of this method are stored in a memory cache.  This improves performance, but
     * because the method uses a simple hashing algorithm it can infrequently have collisions.
     * Setting DboSource::$cacheMethods to false will disable the memory cache.
     *
     * @param mixed $data Either a string with a column to quote. An array of columns to quote or an
     *                    object from DboSource::expression() or DboSource::identifier()
     *
     * @return string SQL field
     */
    public function names($data)
    {
        if (is_object($data) && isset($data->type)) {
            return $data->value;
        }
        if ($data === '*') {
            return '*';
        }
        if (is_array($data)) {
            foreach ($data as $i => $dataItem) {
                $data[$i] = $this->name($dataItem);
            }

            return $data;
        }
        $data = trim($data);
        if (preg_match('/^[\w-]+(?:\.[^ \*]*)*$/', $data)) {
            if (strpos($data, '.') === false) {
                return $this->startQuote.$data.$this->endQuote;
            }
            $items = explode('.', $data);

            return implode($this->endQuote.'.'.$this->startQuote, $items).$this->endQuote;
        }
        if (preg_match('/^[\w-]+\.\*$/', $data)) {
            return $this->startQuote.str_replace('.*', $this->endQuote.'.*', $data);
        }
        if (preg_match('/^([\w-]+)\((.*)\)$/', $data, $matches)) {
            return $matches[1].'('.$this->name($matches[2]).')';
        }
        if (preg_match('/^([\w-]+(\.[\w-]+|\(.*\))*)\s+'.preg_quote($this->alias).'\s*([\w-]+)$/i', $data, $matches)) {
            return preg_replace('/\s{2,}/', ' ', $this->name($matches[1]).' '.$this->alias.' '.$this->name($matches[3]));
        }
        if (preg_match('/^[\w-_\s]*[\w-_]+/', $data)) {
            return $this->startQuote.$data.$this->endQuote;
        }

        return $data;
    }

    /**
     * Quotes and prepares fields and values for an SQL UPDATE statement.
     *
     * @param array $fields
     * @param bool  $quoteValues If values should be quoted, or treated as SQL snippets
     * @param bool  $alias       Include the model alias in the field name
     *
     * @return array Fields and values, quoted and prepared
     */
    protected function prepareUpdateFields($fields, $quoteValues = true, $alias = false)
    {
        $quotedAlias = $this->startQuote.$this->endQuote;

        $updates = [];
        foreach ($fields as $field => $value) {
            if ($alias && strpos($field, '.') === false) {
                $quoted = $model->escapeField($field);
            } elseif (!$alias && strpos($field, '.') !== false) {
                $quoted = $this->name(
                    str_replace(
                        $quotedAlias.'.', '', str_replace(
                            $model->alias.'.', '', $field
                        )
                    )
                );
            } else {
                $quoted = $this->name($field);
            }

            if ($value === null) {
                $updates[] = $quoted.' = NULL';
                continue;
            }
            $update = $quoted.' = ';

            if ($quoteValues) {
                $update .= $this->value($value);
            } elseif (!$alias) {
                $update .= str_replace(
                    $quotedAlias.'.', '', str_replace(
                        $model->alias.'.', '', $value
                    )
                );
            } else {
                $update .= $value;
            }
            $updates[] = $update;
        }

        return $updates;
    }

    /**
     * Format indexes for create table.
     *
     * @param array  $indexes
     * @param string $table
     *
     * @return array
     */
    public function buildIndex($indexes)
    {
        $join = [];
        foreach ($indexes as $name => $value) {
            $out = '';
            if ($name === 'PRIMARY') {
                $out .= 'PRIMARY ';
                $name = null;
            } else {
                if (!empty($value['unique'])) {
                    $out .= 'UNIQUE ';
                }
                $name = $this->startQuote.$name.$this->endQuote;
            }
            if (is_array($value['column'])) {
                $out .= 'KEY '.$name.' ('.implode(', ', array_map([&$this, 'name'], $value['column'])).')';
            } else {
                $out .= 'KEY '.$name.' ('.$this->name($value['column']).')';
            }
            $join[] = $out;
        }

        return $join;
    }

    /**
     * Deletes all the records in a table and resets the count of the auto-incrementing
     * primary key, where applicable.
     *
     * @param mixed $table A string or model class representing the table to be truncated
     *
     * @return bool SQL TRUNCATE TABLE statement, false if not applicable
     */
    public function truncate($table)
    {
        return $this->query('TRUNCATE TABLE '.$table);
    }

    /**
     * Counts the dimensions of an array. If $all is set to false (which is the default) it will
     * only consider the dimension of the first element in the array.
     *
     * @param array $array Array to count dimensions on
     * @param bool  $all   Set to true to count the dimension considering all elements in array
     * @param int   $count Start the dimension count at this number
     *
     * @return int The number of dimensions in $array
     * @static
     */
    protected function countDim($array = null, $all = false, $count = 0)
    {
        if ($all) {
            $depth = [$count];
            if (is_array($array) && reset($array) !== false) {
                foreach ($array as $value) {
                    $depth[] = $this->countDim($value, true, $count + 1);
                }
            }
            $return = max($depth);
        } else {
            if (is_array(reset($array))) {
                $return = $this->countDim(reset($array)) + 1;
            } else {
                $return = 1;
            }
        }

        return $return;
    }

    /**
     * Begin a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started)
     */
    public function begin()
    {
        if ($this->query($this->commands['begin'])) {
            $this->transaction = true;

            return true;
        }

        return false;
    }

    /**
     * Commit a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started)
     */
    public function commit()
    {
        if ($this->query($this->commands['commit'])) {
            $this->transaction = false;

            return true;
        }

        return false;
    }

    /**
     * Rollback a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started)
     */
    public function rollback()
    {
        if ($this->query($this->commands['rollback'])) {
            $this->transaction = false;

            return true;
        }

        return false;
    }

    protected function logSqlError($sql)
    {
        if ($this->container->has('logger')) {
            $message = $this->lastError().' : '.$sql;
            $this->container->get('logger')->error($message);
        }

        return true;
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
        if ($this->transaction) {
            $this->rollback();
        }
        if ($this->connected) {
            $this->close();
        }
    }
}
