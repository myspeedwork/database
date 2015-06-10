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

use Exception;
use Speedwork\Core\Registry;
Use Speedwork\Core\Configure;
use Speedwork\Util\Pagination;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Database
{
    private $connection;
    private $self;
    private $prefix;
    private $query;

    /**
     * Print full query debug info?
     *
     * @var bool
     */
    private $fullDebug = true;

    private $queries = [];

    /**
     * Returns a singleton instance.
     *
     * @return object
     * @static
     */
    public static function getInstance($driver, $signature = '')
    {
        static $instance = [];
        $signature       = $driver.$signature;
        if (!$instance[$signature]) {
            $class                = '\\Speedwork\\Database\\Drivers\\'.ucfirst($driver).'Driver';
            $instance[$signature] = new $class();
        }

        return $instance[$signature];
    }

    /**
     * connect() method makes the actual server connection and selects a database
     * only if needed. This saves database connections.  Multiple database types are
     * supported. Enter your connection credentials in the switch statement below.
     *
     * This is a private function, but it is at the top of the class because you need
     * to enter your connections.
     **/
    public function connect($config = [])
    {
        $this->prefix = $config['prefix'];

        $driver = ($config['driver']) ? $config['driver'] : 'mysql';
        $sig    = md5($config['database'].'_'.$config['host']).$config['sig'];
        $db     = self::getInstance($driver, $sig);

        $db->config       = $config;
        $this->startQuote = $db->startQuote;
        $this->endQuote   = $db->endQuote;
        $this->connected  = $db->connect();
        $this->self       = $db;

        if ($db->connected == false) {
            return false;
        }

        return $db->connection;
    }

    /**
     * disconnect from connection.
     */
    public function disconnect()
    {
        $this->self->disconnect();
    }

    /**
     * Reconnects to database server with optional new settings.
     *
     * @param array $config An array defining the new configuration settings
     *
     * @return bool True on success, false on failure
     */
    public function reconnect($config = [])
    {
        $driver = ($config['driver']) ? $config['driver'] : 'mysql';
        $db     = self::getInstance($driver);
        $db->disconnect();

        return $this->connect($config);
    }

    /**
     * Get the underlying connection object.
     *
     * @return PDOConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * fetch() is used to retrieve a dataset. fetch() determines whether to use the
     * cache or not, and queries either the database or the cache file accordingly.
     *
     * @param string $sql
     * @param int|string cache time
     * @param string $name(optional) if name is empty i will take md5 of the sql
     *
     * @return array|false
     **/
    public function fetch($sql)
    {
        $sql = str_replace('#__', $this->prefix, $sql);

        return $this->getFromDB($sql);
    }

    private function getFromDB($sql)
    {
        $this->query = $sql;
        return $this->self->fetch($sql);
    }

    public function fetchAssoc($sql, $cacheTime = 0, $name = '')
    {
        $data = self::fetch($sql, $cacheTime, $name);

        return $data[0];
    }

    public function numRows()
    {
        return $this->self->numRows();
    }

    /**
     * Begin a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started).
     */
    public function begin()
    {
        return $this->self->rollback();
    }

    /**
     * Commit a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started).
     */
    public function commit()
    {
        return $this->self->rollback();
    }

    /**
     * Rollback a transaction.
     *
     * @param model $model
     *
     * @return bool True on success, false on fail
     *              (i.e. if the database/model does not support transactions,
     *              or a transaction has not started).
     */
    public function rollback()
    {
        return $this->self->rollback();
    }

    /**
     * Use query() to execute INSERT, UPDATE, DELETE statements.
     *
     * @param stirng $sql
     *
     * @return bool
     **/
    public function query($sql)
    {
        $sql             = str_replace('#__', $this->prefix, $sql);
        $this->query = $sql;

        return $this->self->query($sql);
    }

    public function lastError()
    {
        return $this->self->lastError();
    }

    public function insertId()
    {
        return $this->self->insertId();
    }

    public function affectedRows()
    {
        return $this->self->affectedRows();
    }

    public function getTables()
    {
        return $this->self->getTableList();
    }

    public function getTableColumns($tables, $typeonly = true)
    {
        return $this->self->getTableFields($tables, $typeonly);
    }

    /**
     * This is like fetch this will generate query and outout the results.
     *
     * @param string $table
     * @param string $type
     * @param array  $params
     *
     * @return mixed
     **/
    public function find($table, $type = 'first', $params = [])
    {
        $cache      = ($params['cache']) ? $params['cache'] : '';
        $cache_name = ($params['cache_name']) ? $params['cache_name'] : '';

        $params['table'] = $table;
        $params['type']  = $type;
        //dont' conside nagitive values
        if ($params['limit'] < 0) {
            unset($params['limit']);
        }

        $helpers = Registry::get('FindHelpers');

        if ($helpers && is_array($helpers)) {
            $helpers = array_merge($helpers, (array) $params['helper']);
        } else {
            $helpers = $params['helper'];
        }

        if (Configure::read('multisite')) {
            $helpers[] = 'multifind';
        }

        if ($helpers && is_array($helpers)) {
            unset($params['helper']); //we don't require further

            foreach ($helpers as $helper) {
                $help = Registry::get('application')->helper($helper);

                if (method_exists($help, 'beforeFind')) {
                    $res = $help->beforeFind($params);
                    if ($res === false) {
                        return [];
                    }
                    //if stop further
                    if ($params['stop'] === true) {
                        break;
                    }

                    $return = $params['result'];
                }
            }

            $type  = $params['type'];
            $table = $params['table'];
        }

        $params['table'] = $table;
        $params['type']  = $type;

        switch ($type) {
            case 'count':

                $params['fields'] = ['count(*) as total'];
                unset($params['limit'], $params['order'], $params['offset'], $params['page']);
                $query = $this->self->buildStatement($params, $table);

                if ($params['group']) {
                    $query = 'SELECT COUNT(*) AS total FROM ('.$query.') as tmp';
                }
                $data   = $this->fetch($query, $cache, $cache_name);
                $return = intval($data[0]['total']);
                unset($data);
                break;
            case 'all':
                $query  = $this->self->buildStatement($params, $table);
                $return = $this->fetch($query, $cache, $cache_name);
                break;
            /*
             * array('fields' => array('value','group','title'),
             *       'empty' => array('module','mod_view'),
             *       'replace' => array('parent_id','name')
             *       );
             */
            case 'list':
                $query = $this->self->buildStatement($params, $table);
                $rows  = $this->fetch($query, $cache, $cache_name);

                $fields = [];
                if (is_array($rows[0])) {
                    $fields = array_keys($rows[0]);
                }

                if (count($fields) == 0) {
                    $return = &$rows;
                } else {
                    $empty   = $params['empty'];
                    $replace = $params['replace'];

                    $field1 = $fields[0];
                    $field2 = $fields[1];
                    $field3 = $fields[2];
                    $res    = [];
                    foreach ($rows as $row) {
                        if (count($fields) == 1) {
                            $res[$row[$field1]] = $row[$field1];
                        } elseif (count($fields) == 2) {
                            $res[$row[$field1]] = $row[$field2];
                        } else {
                            if ($empty) {
                                $v  = $empty[0];
                                $re = $empty[1];

                                if (!$row[$v] && $field2 == $re) {
                                    $res[$row[$re]][$row[$field1]] = $row[$field2];
                                } elseif (!$row[$v] && $field3 == $re) {
                                    $res[$row[$field3]][$row[$field1]] = $row[$re];
                                } else {
                                    $res[$row[$field3]][$row[$field1]] = $row[$field2];
                                }
                            } else {
                                $res[$row[$field3]][$row[$field1]] = $row[$field2];
                            }

                            if ($replace) {
                                $res[$row[$field2]][$row[$field1]] = $row[$field3];
                            }
                        }
                    }
                    unset($field1, $field2, $rows, $field3, $v, $re);
                    $return = &$res;
                }
                break;
            /*
                $database->find('menu','neighbors',array('field'=>ordering,'value'=>1));
             */
            case 'neighbors':
            case 'siblings':

                $field = $params['field'];
                $value = $params['value'];
                unset($params['value'], $params['field']);

                if (empty($params['limit'])) {
                    $params['limit'] = 1;
                }

                if (!is_array($params['conditions'])) {
                    $params['conditions'] = [];
                }

                //build first query
                $params['order']      = [$field.' DESC'];
                $params['conditions'] = array_merge([$field.' < ' => $value], $params['conditions']);

                $query = $this->self->buildStatement($params, $table);
                $data  = $this->fetch($query, $cache, $cache_name);

                $return['prev'] = ($params['limit']) ? $data[0] : $data;

                //build second query
                $params['order']      = [$field];
                $params['conditions'] = array_merge([$field.' > ' => $value], $params['conditions']);

                $query = $this->self->buildStatement($params, $table);
                $data  = $this->fetch($query, $cache, $cache_name);

                $return['next'] = ($params['limit']) ? $data[0] : $data;

                break;
            /*
                $html =  '<li><input type="checkbox" name="category[]" class="liChild" value="{menuID}"/>{name}';
                $database->find('menu','threaded',
                                    array('order'=>array('parent_id', 'ordering'),
                                          'html'=>array($html,'</li>')
                                          'parent'=>0,
                                          'field'=>array('primary_key','parent_id'),
                                          'parent_tag'=>array('<ul>','</ul>'))
                                );
             */
            case 'threaded':

                if (!$params['field']) {
                    trace(2, 'field not found');
                }

                $query    = $this->self->buildStatement($params, $table);
                $data     = $this->fetch($query, $cache, $cache_name);
                $menuData = ['items' => [],'parents' => []];
                foreach ($data as $menuItem) {
                    // Creates entry into items array with current menu item id ie. $menuData['items'][1]
                    $menuData['items'][$menuItem[$params['field'][0]]] = $menuItem;
                    // Creates entry into parents array. Parents array contains a list of all items with children
                    $menuData['parents'][$menuItem[$params['field'][1]]][] = $menuItem[$params['field'][0]];
                }
                $return = self::buildThreaded($params['parent'], $menuData, $params['html'], $params['parent_tag']);
                break;

            case 'first':
                $params['limit'] = 1;
                $query           = $this->self->buildStatement($params, $table);
                $return          = $this->fetch($query, $cache, $cache_name);
                $return          = $return[0];
                break;
            case 'field':
                $query  = $this->self->buildStatement($params, $table);
                $d      = $this->fetch($query, $cache, $cache_name);
                $return = [];
                foreach ($d as $key => $v) {
                    $return[$key][] = $v;
                }
                unset($d);
                break;
        }

        //if method exists
        if ($helpers && is_array($helpers)) {
            foreach ($helpers as $helper) {
                $help = Registry::get('application')->helper($helper);
                if (method_exists($help, 'afterFind')) {
                    $return = $help->afterFind($return, $params);
                }
            }
        }

        return $return;
    }

    /*
     * bulid threaded items
     * @param mixed $parent
     * @param array $menuData
     * @param string $ht ($html)
     * @param array $parentTag
    */

    private function buildThreaded($parent, $menuData, $replace, $parentTag)
    {
        $html = '';
        $html .= $parentTag[0];

        $replace = (array) $replace;
        $ht      = $replace[0];
        $end_tag = $replace[1];

        if (!$ht) {
            throw new Exception('html not found');
        }
        if (isset($menuData['parents'][$parent])) {
            foreach ($menuData['parents'][$parent] as $itemId) {
                $data    = $menuData['items'][$itemId];
                $hts     = $ht;
                $matches = [];
                preg_match_all('~\{([^{}]+)\}~', $ht, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $k   = $match[0];
                    $v   = $data[$match[1]];
                    $hts = str_replace($k, $v, $hts);
                }
                $html .=  $hts;
                if (isset($menuData['parents'][$itemId])) {
                    $html .= self::buildThreaded($itemId, $menuData, $replace, $parentTag);
                }
                $html .=  $end_tag;
            }
        }
        $html .= $parentTag[1];

        return $html;
    }

    /**
     * paginate.
     */
    public function paginate($table, $type = 'all', $params = [])
    {
        $pagingtype = ($params['pagingtype']) ? $params['pagingtype'] : 'mixed';

        $page = ($params['page'] && is_numeric($params['page']))
                    ? $params['page']
                    : !empty($_REQUEST['page']) && is_numeric($_REQUEST['page'])
                    ? $_REQUEST['page']
                    : 1;

        $limit = $params['limit'];

        if ($limit === false) {
            $limit = $params['llimit'];
        }

        $limit = ($limit && is_numeric($limit)) ? $limit : 25;

        if (!empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit'])) {
            $limit = $_REQUEST['limit'];
        }

        $limit_start = $limit * ($page - 1);

        $runFind     = true;
        $total_check = false;

        if ($params['total']) {
            $total       = $params['total'];
            $total_check = true;
        } else {
            if (($pagingtype != 'ajax' && $pagingtype != 'mixed') ||
              (($pagingtype == 'ajax' || $pagingtype == 'mixed') &&
               $page == 1)) {
                $total       = self::find($table, 'count', $params);
                $total_check = true;
            }
        }

        $params['limit'] = $limit;
        $params['page']  = $page;

        if ($runFind) {
            $data = self::find($table, $type, $params);

            if ($data['total']) {
                $total = $data['total'];
                $extra = $data['extra'];
                $data  = $data['data'];
            }

            $i = $limit_start;
            if (is_array($data)) {
                foreach ($data as &$row) {
                    $row['serial'] = ++$i;
                }
            }
        } else {
            $data = [];
        }

        $nowTotal = count($data);
        $rtotal   = $_REQUEST['total'];
        $total    = ($total_check == true) ? $total : (($rtotal) ? $rtotal : $nowTotal);
        $next     = ($nowTotal >= $limit) ? $page + 1 : false;

        $is_api_request = Registry::get('is_api_request');

        if ($is_api_request) {
            $pagingtype = 'api';

            return [
                'data'       => $data,
                'now'        => $nowTotal,
                'next'       => $next,
                'page'       => $page,
                'limit'      => $limit,
                'limitstart' => $limit_start,
                'total'      => $total,
                'extra'      => $extra,
            ];
        }

        $pagination = new Pagination($pagingtype);

        $paging = $pagination->render($page, $total, $limit, $nowTotal);

        $paging['data']  = $data;
        $paging['extra'] = $extra;

        return $paging;
    }

    /**
     * function save is used to save the associate array to database.
     *
     * @param string $table
     * @param array  $data
     *
     * @return bool
     **/
    public function save($table, $data = [], $details = [])
    {
        if (count($data) == 0) {
            return false;
        }

        //check is there any callback helper in this Query
        $helpers = Registry::get('SaveHelpers');

        if (Configure::read('multisite')) {
            $helpers[] = 'multisave';
        }

        if ($helpers && is_array($helpers)) {
            unset($params['helper']); //we don't require further

            foreach ($helpers as $helper) {
                $help = Registry::get('application')->helper($helper);

                if (method_exists($help, 'beforeSave')) {
                    $res = $help->beforeSave($data, $table, $details);
                    if ($res === false) {
                        return true;
                    }

                    if ($details['stop'] === true) {
                        break;
                    }
                }
            }
        }
        //end of helpers

        $k = $v = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $va = [];
                foreach ($value as $k2 => $v2) {
                    $va[] = ($v2) ? $this->self->value($v2) : "''";
                    $k[]  = $this->self->name($k2);
                }
                $v[] = '('.@implode(',', $va).')';
            } else {
                $v[] = ($value) ? $this->self->value($value) : "''";
                $k[] = $this->self->name($key);
            }
        }

        $k = array_unique($k);

        $params           = [];
        $params['table']  = $table;
        $params['fields'] = $k;
        $params['values'] = $v;
        $query  = $this->self->buildStatement($params, $table, 'insert');

        return $this->query($query);
    }

    /**
     * Alias function for save.
     */
    public function saveAll($table, &$data = [])
    {
        return $this->save($table, $data);
    }

    /**
     * Alias function for save.
     */
    public function insert($table, $data = [])
    {
        return $this->save($table, $data);
    }
    /**
     * Update Table based on condition with data.
     *
     * @param string $table
     * @param array  $data
     * @param array  $conditions
     *
     * @return bool
     **/
    public function update($table, $data = [], $conditions = [], $details = [])
    {
        if (count($data) == 0) {
            return false;
        }

        $params               = [];
        $params['table']      = $table;
        $params['fields']     = $data;
        $params['conditions'] = $conditions;

        //check is there any callback helper in this Query
        $helpers = Registry::get('UpdateHelpers');

        if (Configure::read('multisite')) {
            $helpers[] = 'multiupdate';
        }

        if ($helpers && is_array($helpers)) {
            unset($params['helper']); //we don't require further

            foreach ($helpers as $helper) {
                $help = Registry::get('application')->helper($helper);

                if (method_exists($help, 'beforeUpdate')) {
                    $res = $help->beforeUpdate($params, $details);
                    if ($res === false) {
                        return true;
                    }

                    if ($details['stop'] === true) {
                        break;
                    }
                }
            }
            $table = $params['table'];
        }
        //end of helpers

        $k = [];
        foreach ($params['fields'] as $key => $value) {
            if ($key && !is_numeric($key)) {
                $k[] = $this->self->name($key).' = '.$this->self->value($value);
            } else {
                $k[] = $value;
            }
        }
        $params['fields'] = $k;

        $query = $this->self->buildStatement($params, $table, 'update');

        return $this->query($query);
    }

    /**
     * DELETE Records from table based on condition.
     *
     * @param string             $table
     * @param array() (optional) $conditions
     *
     * @return bool
     **/
    public function delete($table, $conditions = [], $limit = false)
    {
        $params               = [];
        $params['table']      = $table;
        $params['conditions'] = $conditions;
        if ($limit) {
            $params['limit'] = $limit;
        }

        //check is there any callback helper in this Query
        $helpers = Registry::get('DeleteHelpers');
        if ($helpers && is_array($helpers)) {
            $helpers = array_merge($helpers, (array) $params['helper']);
        } else {
            $helpers = $params['helper'];
        }

        if (Configure::read('multisite')) {
            $helpers[] = 'multidelete';
        }

        if ($helpers && is_array($helpers)) {
            unset($params['helper']); //we don't require further

            foreach ($helpers as $helper) {
                $help = Registry::get('application')->helper($helper);

                if (method_exists($help, 'beforeDelete')) {
                    $res = $help->beforeDelete($params);
                    if ($res === false) {
                        return true;
                    }

                    if ($params['stop'] === true) {
                        break;
                    }
                }
            }
            $table = $params['table'];
        }
        //end of helpers

        $query = $this->self->buildStatement($params, $table, 'delete');

        return $this->query($query);
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

    public function securesql($value)
    {
        return $this->self->securesql($value);
    }

    /**
     * Alias function for buildStatement.
     *
     * @param string $table
     * @param array  $params
     */
    public function buildQuery($table, $params = [], $type = 'select')
    {
        return $this->self->buildStatement($params, $table, $type);
    }

    /**
     * Alias function for buildStatement.
     *
     * @param string $table
     * @param array  $params
     */
    public function buildConditions($conditions = [])
    {
        return $this->self->conditions($conditions);
    }

    /**
     * Output information about an SQL query. The SQL statement, number of rows in resultset,
     * and execution time in microseconds. If the query fails, an error is output instead.
     *
     * @param string $sql Query to show information on.
     */
    public function showQuery($echo = false)
    {
        $error = $this->lastError();
        $r     = '<p>';
        if ($error) {
            $r .= "<span style = \"color:Red;\"><b>SQL Error:</b> {$error}</span>";
        }
        $r .= '<b>Query:</b> '.$this->sql_query;
        $r .= '</p>';

        if ($echo) {
            echo $r;
        } else {
            return $r;
        }
    }
}
