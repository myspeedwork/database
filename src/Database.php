<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database;

use Exception;
use Speedwork\Core\Di;
use Speedwork\Database\Event\DatabaseEvents;
use Speedwork\Database\Event\RequestEvent;
use Speedwork\Database\Event\ResponseEvent;
use Speedwork\Util\Pagination;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Database extends Di
{
    protected $cache     = false;
    protected $connected = false;
    protected $config    = [];
    protected $prefix;
    protected $sql;
    protected $driver;

    /**
     * Set configuration to create database connection object.
     *
     * @param array $config Db configuration
     * @param bool  $reset  Reset the old config or not
     */
    public function setConfig($config = [], $reset = true)
    {
        if (is_array($config)) {
            if ($reset) {
                $this->config = $config;
            } else {
                $this->config = array_merge($this->config, $config);
            }
        }

        return $this;
    }

    /**
     * Get the stored configuration.
     *
     * @return array List of configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Connect to the database driver.
     *
     * @return object Return database driver object
     */
    public function connect()
    {
        $config = $this->config;

        $driver       = ($config['driver']) ? $config['driver'] : 'mysqli';
        $this->cache  = $config['cache'];
        $this->prefix = $config['prefix'];

        $database = '\\Speedwork\\Database\\Drivers\\'.ucfirst($driver).'Driver';

        try {
            $this->driver = new $database();
            $this->driver->setConfig($config);
            $this->driver->setContainer($this->getContainer());
            $this->connected = $this->driver->connect();
        } catch (Exception $e) {
            $this->connected = false;
            throw new Exception($e->getMessage());
        }

        return $this->driver;
    }

    /**
     * Check whether connect made was success or not.
     *
     * @return bool Return true on success and false on fail
     */
    public function isConnected()
    {
        if ($this->connected === false) {
            return false;
        }

        $this->connected = $this->driver->query('SELECT 1');

        return $this->connected;
    }

    /**
     * Disconnect from driver.
     */
    public function disConnect()
    {
        $this->driver->disConnect();
        $this->connected = false;
    }

    /**
     * Reconnects to database server with optional new settings.
     *
     * @param array $config An array defining the new configuration settings
     *
     * @return bool True on success, false on failure
     */
    public function reConnect()
    {
        if ($this->connected === false) {
            return false;
        }

        $connected = $this->driver->query('SELECT 1');
        if ($connected) {
            return true;
        }

        $this->disconnect();

        return $this->connect();
    }

    /**
     * fetch() is used to retrieve a dataset. fetch() determines whether to use the
     * cache or not, and queries either the database or the cache file accordingly.
     *
     * @param string     $sql
     * @param int|string $duration       cache time
     * @param string     $name(optional) if name is empty i will take md5 of the sql
     *
     * @return array
     **/
    public function fetch($sql, $duration = null, $name = null)
    {
        if ($duration === 'daily') {
            $duration = '+1 DAY';
        }

        $sql = str_replace('#__', $this->prefix, $sql);

        $this->sql = $sql;

        if ($this->cache !== true || empty($duration)) {
            return $this->fetchFromDb($sql);
        }

        $cache_key = $name ?: md5($sql);
        $cache_key = 'db_'.$cache_key;

        return $this->get('cache')->remember(
            $cache_key, function () use ($sql) {
                return $this->fetchFromDb($sql);
            }, $duration
        );
    }

    /**
     * Retrive the all record from dataset.
     *
     * @param string     $sql            Database query
     * @param int|string $duration       cache time
     * @param string     $name(optional) if name is empty i will take md5 of the sql
     *
     * @return array Results set
     */
    public function fetchAll($sql, $duration = null, $name = null)
    {
        return $this->fetch($sql, $duration, $name);
    }

    /**
     * Retrive the single record from dataset.
     *
     * @param string     $sql            Database query
     * @param int|string $duration       cache time
     * @param string     $name(optional) if name is empty i will take md5 of the sql
     *
     * @return array Result set
     */
    public function fetchAssoc($sql, $duration = null, $name = null)
    {
        $data = $this->fetch($sql, $duration, $name);

        return $data[0];
    }

    /**
     * Retrive the results set from database driver.
     *
     * @param string $sql Sql Query
     *
     * @return array Results set
     */
    protected function fetchFromDb($sql)
    {
        return $this->driver->fetch($sql);
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
        return $this->driver->begin();
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
        return $this->driver->commit();
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
        return $this->driver->rollback();
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
        $sql       = str_replace('#__', $this->prefix, $sql);
        $this->sql = $sql;

        return $this->driver->query($sql);
    }

    /**
     * Returns the last database error.
     *
     * @return string
     */
    public function lastError()
    {
        return $this->driver->lastError();
    }

    /**
     * Returns the ID generated from the previous INSERT operation.
     *
     * @return mixed Last ID key generated in previous INSERT
     */
    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    /**
     * Returns the number of rows returned by last operation.
     *
     * @return int Number of rows returned by last operation
     */
    public function lastNumRows()
    {
        return $this->driver->lastNumRows();
    }

    /**
     * Returns the number of rows affected by last query.
     *
     * @return int Number of rows affected by last query
     */
    public function lastAffected()
    {
        return $this->driver->lastAffected();
    }

    public function getTableList()
    {
        return $this->driver->getTableList();
    }

    public function getTableFields($tables, $typeonly = true)
    {
        return $this->driver->getTableFields($tables, $typeonly);
    }

    /**
     * This is like fetch this will generate query and output the results.
     *
     *
     * $list = array('fields' => array('value','group','title'),
     *       'empty' => array('module','mod_view'),
     *       'replace' => array('parent_id','name')
     *       );
     *
     * $database->find('menu','neighbors',array('field'=>ordering,'value'=>1));
     *
     * @param string $table
     * @param string $type
     * @param array  $params
     *
     * @return mixed
     **/
    public function find($table, $type = 'first', $params = [])
    {
        if (is_array($table)) {
            return $this->findTables($table, $type, $params);
        }

        $params['table'] = $table;
        $params['type']  = $type;

        $event = new RequestEvent($params, []);
        $this->app['events']->dispatch(DatabaseEvents::BEFORE_FIND, $event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        $params = $event->getParams();

        $cache      = ($params['cache']) ? $params['cache'] : '';
        $cache_name = ($params['cache_name']) ? $params['cache_name'] : '';

        //don't consider nagitive values
        if ($params['limit'] < 0) {
            unset($params['limit']);
        }

        $type  = $params['type'];
        $table = $params['table'];

        $params['table'] = $table;
        $params['type']  = $type;

        switch ($type) {
            case 'count':
                $params['fields'] = ['count(*) as total'];
                unset($params['limit'], $params['order'], $params['offset'], $params['page']);
                $query = $this->driver->buildStatement($params, $table);

                if ($params['group']) {
                    $query = 'SELECT COUNT(*) AS total FROM ('.$query.') as tmp';
                }
                $data    = $this->fetch($query, $cache, $cache_name);
                $results = intval($data[0]['total']);
                unset($data);
                break;

            case 'all':
                $query   = $this->driver->buildStatement($params, $table);
                $results = $this->fetch($query, $cache, $cache_name);
                break;

            case 'list':
                $query = $this->driver->buildStatement($params, $table);
                $rows  = $this->fetch($query, $cache, $cache_name);

                $fields = [];
                if (is_array($rows[0])) {
                    $fields = array_keys($rows[0]);
                }

                if (count($fields) == 0) {
                    $results = &$rows;
                } else {
                    $empty   = $params['empty'];
                    $replace = $params['replace'];

                    $value  = $fields[0];
                    $option = $fields[1];
                    $optgrp = $fields[2];
                    $res    = [];
                    foreach ($rows as $row) {
                        if (count($fields) == 1) {
                            $res[$row[$value]] = $row[$value];
                        } elseif (count($fields) == 2) {
                            $res[$row[$value]] = $row[$option];
                        } else {
                            if ($empty) {
                                $v  = $empty[0];
                                $re = $empty[1];

                                if (!$row[$v] && $option == $re) {
                                    $res[$row[$re]][$row[$value]] = $row[$option];
                                } elseif (!$row[$v] && $optgrp == $re) {
                                    $res[$row[$optgrp]][$row[$value]] = $row[$re];
                                } else {
                                    $res[$row[$optgrp]][$row[$value]] = $row[$option];
                                }
                            } else {
                                $res[$row[$optgrp]][$row[$value]] = $row[$option];
                            }

                            if ($replace) {
                                $res[$row[$option]][$row[$value]] = $row[$optgrp];
                            }
                        }
                    }
                    unset($value, $option, $rows, $optgrp, $v, $re);
                    $results = &$res;
                }
                break;
            case 'neighbors':
            case 'siblings':
                $field = $params['field'];
                $value = $params['value'];
                unset($params['value'], $params['field']);

                if (empty($params['limit'])) {
                    $params['limit'] = 1;
                }

                if (!is_array($params['conditions'])) {
                    $conditions = [];
                } else {
                    $conditions = $params['conditions'];
                }

                //build first query
                $params['order']      = [$field.' DESC'];
                $params['conditions'] = array_merge([$field.' < ' => $value], $conditions);

                $query = $this->driver->buildStatement($params, $table);
                $data  = $this->fetch($query, $cache, $cache_name);

                $results['prev'] = ($params['limit']) ? $data[0] : $data;

                //build second query
                $params['order']      = [$field];
                $params['conditions'] = array_merge([$field.' > ' => $value], $conditions);

                $query = $this->driver->buildStatement($params, $table);
                $data  = $this->fetch($query, $cache, $cache_name);

                $results['next'] = ($params['limit']) ? $data[0] : $data;

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
                    throw new Exception('field not found');
                }

                $query = $this->driver->buildStatement($params, $table);
                $data  = $this->fetch($query, $cache, $cache_name);

                $menuData = ['items' => [], 'parents' => []];
                foreach ($data as $menuItem) {
                    // Creates entry into items array with current menu item id ie. $menuData['items'][1]
                    $menuData['items'][$menuItem[$params['field'][0]]] = $menuItem;
                    // Creates entry into parents array. Parents array contains a list of all items with children
                    $menuData['parents'][$menuItem[$params['field'][1]]][] = $menuItem[$params['field'][0]];
                }
                $results = $this->buildThreaded($params['parent'], $menuData, $params['html'], $params['parent_tag']);
                break;

            case 'first':
                $params['limit'] = 1;
                $query           = $this->driver->buildStatement($params, $table);
                $results         = $this->fetch($query, $cache, $cache_name);
                $results         = $results[0];
                break;

            case 'field':
                $query   = $this->driver->buildStatement($params, $table);
                $rows    = $this->fetch($query, $cache, $cache_name);
                $results = [];
                foreach ($rows as $key => $v) {
                    $results[$key][] = $v;
                }
                unset($rows);
                break;
        }

        $event = new ResponseEvent($event, $results, $query);
        $this->app['events']->dispatch(DatabaseEvents::AFTER_SAVE, $event);

        return $event->getResults();
    }

    /**
     * bulid threaded items.
     *
     * @param mixed  $parent
     * @param array  $menuData
     * @param string $ht        ($html)
     * @param array  $parentTag
     **/
    protected function buildThreaded($parent, $menuData, $replace, $parentTag)
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
                $html .= $hts;
                if (isset($menuData['parents'][$itemId])) {
                    $html .= $this->buildThreaded($itemId, $menuData, $replace, $parentTag);
                }
                $html .= $end_tag;
            }
        }
        $html .= $parentTag[1];

        return $html;
    }

    protected function findTables($tables = [], $type = 'all', $params = [])
    {
        if ($type == 'count') {
            $total = 0;

            foreach ($tables as $table) {
                $total += $this->find($table, $type, $params);
            }

            return $total;
        }

        if ($type == 'first') {
            foreach ($tables as $table) {
                $rows = $this->find($table, $type, $params);
                if (!empty($rows)) {
                    return $rows;
                }
            }
        }

        if ($type == 'all') {
            $total = 0;
            $data  = [];
            $limit = $params['limit'];

            foreach ($tables as $table) {
                $rows = $this->find($table, 'all', $params);

                $data = array_merge($data, $rows);

                if ($limit) {
                    $total += count($rows);

                    if ($total >= $limit) {
                        break;
                    } else {
                        // get remaings results from other tables
                        $params['limit'] = $limit - $total;
                    }
                }
            }

            unset($rows);

            return $data;
        }

        return $this->find($table, $type, $params);
    }

    /**
     * paginate.
     */
    public function paginate($tables, $type = 'all', $params = [])
    {
        if (is_array($type)) {
            $params = $type;
        }

        $paging = $params['paging'] ?: $params['pagingtype'];
        $paging = $paging ?: 'mixed';

        $is_api_request = $this->get('is_api_request');

        if ($is_api_request) {
            $paging = 'api';
        }

        $page = ($this->data['page'] && is_numeric($this->data['page'])) ? $this->data['page'] : $params['page'];
        $page = ($page && is_numeric($page)) ? $page : 1;

        $limit = ($this->data['limit'] && is_numeric($this->data['limit'])) ? $this->data['limit'] : $params['limit'];
        $limit = ($limit && is_numeric($limit)) ? $limit : 25;

        $limit_start = $limit * ($page - 1);

        $params['limit'] = $limit;
        $params['page']  = $page;

        $hasTotal = false;
        $total    = 0;
        $nowTotal = 0;
        $data     = [];

        if (!is_array($tables)) {
            $tables = [$tables];
        }

        if ($params['total']) {
            $total    = $params['total'];
            $hasTotal = true;
        } else {
            if (($paging != 'scroll' && $paging != 'mixed')
                || (($paging == 'scroll' || $paging == 'mixed')
                && $page == 1)
            ) {
                foreach ($tables as $table) {
                    $total += $this->find($table, 'count', $params);
                }

                $hasTotal = true;
            }
        }

        foreach ($tables as $table) {
            $rows = $this->find($table, 'all', $params);

            if ($nowTotal) {
                $data = array_merge($data, $rows);
            } else {
                $data = $rows;
            }

            $nowTotal += count($rows);

            if ($nowTotal >= $limit) {
                break;
            } else {
                // get remaings results from other tables
                $params['limit'] = $limit - $nowTotal;
            }
        }

        unset($rows);

        $i = $limit_start;
        if (is_array($data)) {
            foreach ($data as &$row) {
                $row['serial'] = ++$i;
            }
        }

        $total = ($hasTotal) ? $total : $this->data['total'];
        $total = $total ?: $nowTotal;

        $pagination = new Pagination();
        $pagination->setType($paging);

        $respose           = [];
        $respose['total']  = $total;
        $respose['data']   = $data;
        $respose['paging'] = $pagination->render($page, $total, $limit, $nowTotal);

        return $respose;
    }

    /**
     * function save is used to save the associate array to database.
     *
     * @param string $table
     * @param array  $data
     *
     * @return bool
     **/
    public function save($table, $values = [], $details = [])
    {
        if (count($values) == 0) {
            return false;
        }

        if (!is_array($values[0])) {
            $values = [$values];
        }

        $params           = [];
        $params['table']  = $table;
        $params['values'] = $values;

        $event = new RequestEvent($params, $details);
        $this->app['events']->dispatch(DatabaseEvents::BEFORE_SAVE, $event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        $params = $event->getParams();

        $fields = array_keys($params['values'][0]);

        $values = [];
        foreach ($params['values'] as $value) {
            $va = [];
            foreach ($value as $v2) {
                $va[] = $this->driver->value($v2);
            }
            $values[] = '('.implode(',', $va).')';
        }

        $params['fields'] = $fields;
        $params['values'] = $values;

        $query   = $this->driver->buildStatement($params, $params['table'], 'insert');
        $results = $this->query($query);

        $event = new ResponseEvent($event, $results, $query);
        $this->app['events']->dispatch(DatabaseEvents::AFTER_SAVE, $event);

        return $event->getResults();
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
    public function update($table, $fields = [], $conditions = [], $details = [])
    {
        if (count($fields) == 0) {
            return false;
        }

        $params               = [];
        $params['table']      = $table;
        $params['fields']     = $fields;
        $params['conditions'] = $conditions;

        $event = new RequestEvent($params, $details);
        $this->app['events']->dispatch(DatabaseEvents::BEFORE_UPDATE, $event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        $params = $event->getParams();

        $k = [];
        foreach ($params['fields'] as $key => $value) {
            if ($key && !is_numeric($key)) {
                $k[] = $this->driver->name($key).' = '.$this->driver->value($value);
            } else {
                $k[] = $value;
            }
        }
        $params['fields'] = $k;

        $query   = $this->driver->buildStatement($params, $params['table'], 'update');
        $results = $this->query($query);

        $event = new ResponseEvent($event, $results, $query);
        $this->app['events']->dispatch(DatabaseEvents::AFTER_UPDATE, $event);

        return $event->getResults();
    }

    public function cascade($table, $data = [], $conditions = [], $details = [])
    {
        $rows = $this->find(
            $table, 'count', [
            'conditions' => $conditions,
            ]
        );

        if ($rows > 0) {
            return $this->update($table, $data, $conditions, $details);
        }

        if (count(array_filter($data))) {
            return $this->save($table, $data, $details);
        }
    }

    /**
     * DELETE Records from table based on condition.
     *
     * @param string             $table
     * @param array() (optional) $conditions
     *
     * @return bool
     **/
    public function delete($table, $conditions = [], $details = [])
    {
        $params               = [];
        $params['table']      = $table;
        $params['conditions'] = $conditions;
        if ($details['limit']) {
            $params['limit'] = $details['limit'];
        }

        $event = new RequestEvent($params, $details);
        $this->app['events']->dispatch(DatabaseEvents::BEFORE_DELETE, $event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        $params = $event->getParams();

        $query   = $this->driver->buildStatement($params, $params['table'], 'delete');
        $results = $this->query($query);

        $event = new ResponseEvent($event, $results, $query);
        $this->app['events']->dispatch(DatabaseEvents::AFTER_DELETE, $event);

        return $event->getResults();
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

    public function escape($value)
    {
        return $this->driver->escape($value);
    }

    /**
     * Alias function for buildStatement.
     *
     * @param string $table
     * @param array  $params
     */
    public function buildQuery($table, $params = [], $type = 'select')
    {
        return $this->driver->buildStatement($params, $table, $type);
    }

    /**
     * Alias function for buildStatement.
     *
     * @param string $table
     * @param array  $params
     */
    public function buildConditions($conditions = [])
    {
        return $this->driver->conditions($conditions);
    }

    /**
     * Output information about an SQL query. The SQL statement, number of rows in resultset,
     * and execution time in microseconds. If the query fails, an error is output instead.
     *
     * @param string $sql Query to show information on
     */
    public function showQuery($echo = false)
    {
        $error = $this->lastError();
        $r     = '<p>';
        if ($error) {
            $r .= "<span style = \"color:Red;\"><b>SQL Error:</b> {$error}</span>";
        }
        $r .= '<b>Query:</b> '.$this->sql;
        $r .= '</p>';

        if ($echo) {
            echo $r;
        } else {
            return $r;
        }
    }
}
