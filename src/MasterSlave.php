<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */
namespace Speedwork\Database;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class MasterSlave extends Database
{
    protected $connections = [];
    protected $params      = [];
    protected $split       = false;
    protected $transaction = false;

    public function __construct($params = [])
    {
        if (!isset($params['slaves']) || !isset($params['master'])) {
            throw new \InvalidArgumentException('master or slaves configuration missing');
        }
        if (count($params['slaves']) == 0) {
            throw new \InvalidArgumentException('You have to configure at least one slaves.');
        }

        $config = [];

        $config['master'] = $params['master'];
        $slaves           = $params['slaves'];
        $masters          = $params['masters'];

        unset($params['slaves'], $params['master'], $params['masters']);

        $config['slave'] = $slaves[mt_rand(0, count($slaves) - 1)];
        if (is_array($masters)) {
            $config['master'] = $masters[mt_rand(0, count($masters) - 1)];
        }

        $config['slave']  = array_merge($params, $config['slave']);
        $config['master'] = array_merge($params, $config['master']);

        if (!empty($config['connections'])) {
            $this->split = true;
        }

        $this->params = $config;
    }

    public function connect()
    {
        $connectTo = $this->getConnection('SELECT', 'slave');

        return $this->connectTo($connectTo);
    }

    public function connectTo($name = null)
    {
        $name = $name ?: 'slave';

        if (isset($connections[$name])) {
            return $connections[$name];
        }

        if ($name !== 'slave' && $name !== 'master') {
            throw new \InvalidArgumentException('Invalid option to connect(), only master or slave allowed.');
        }

        $config = $this->params[$name];

        $this->setConfig($config);
        $this->connections[$name] = parent::connect();

        return $this->connections[$name];
    }

    public function getFromDB($sql)
    {
        if ($this->transaction) {
            $connectTo = 'master';
        } else {
            $connectTo = $this->getConnection($sql, 'slave');
        }

        $this->connectTo($connectTo);

        return parent::getFromDB($sql);
    }

    public function query($sql)
    {
        $parts = explode(' ', $sql, 2);
        $type  = trim(strtoupper($parts[0]));

        if ($type == 'DROP') {
            $this->transaction = false;
        }

        if ($type == 'CREATE') {
            $this->transaction = true;
        }

        if (!$this->transaction) {
            if ($type == 'SELECT' || $type == 'SET') {
                $connectTo = 'slave';
            } else {
                $connectTo = 'master';
            }
            $connectTo = $this->getConnection($sql, $connectTo);
        } else {
            $connectTo = 'master';
        }

        $this->connectTo($connectTo);

        return parent::query($sql);
    }

    protected function getConnection($sql, $default = 'slave')
    {
        if (!$this->split) {
            return $default;
        }

        $sql  = explode(' ', $sql, 2);
        $type = trim(strtolower($sql[0]));

        $connections = $this->params['connections'];
        if (isset($connections[$type])) {
            return $connections[$type];
        }

        return $connections['other'];
    }
}
