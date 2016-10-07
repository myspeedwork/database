<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database\EventListener;

use Speedwork\Container\Container;
use Speedwork\Database\Event\DatabaseEvents;
use Speedwork\Database\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class RequestListener implements EventSubscriberInterface
{
    /**
     * Application Instance.
     *
     * @var \Speedwork\Container\Container
     */
    protected $app;

    /**
     * Tables name those should be changed.
     *
     * @var array
     */
    protected $tables = [];

    public function __construct(Container $app)
    {
        $this->app    = $app;
        $this->tables = $this->app['config']->get('database.tables');
    }

    /**
     * Modify the request before find.
     *
     * @param RequestEvent $event
     */
    public function onFindRequest(RequestEvent $event)
    {
        $userid = $this->app['userid'];
        $params = $event->getParams();

        if ($params['ignore'] === true || empty($userid)) {
            return true;
        }

        $tables = array_merge($this->tables['default'], $this->tables['find']);
        $table  = str_replace('#__', '', $params['table']);

        $column = $tables[$table];
        if (!isset($column)) {
            return true;
        }

        $conditions   = $params['conditions'];
        $alias        = ($params['alias']) ? $params['alias'].'.' : '';
        $conditions[] = [$alias.$column => $userid];

        $params['conditions'] = $conditions;

        $event->setParams($params);

        return true;
    }

    /**
     * @param RequestEvent $event
     *
     * @return bool
     */
    public function onUpdateRequest(RequestEvent $event)
    {
        $userid  = $this->app['userid'];
        $params  = $event->getParams();
        $details = $event->getDetails();

        if ($details['ignore'] === true || empty($userid)) {
            return true;
        }

        $tables = array_merge($this->tables['default'], $this->tables['update']);
        $table  = str_replace('#__', '', $params['table']);

        $column = $tables[$table];
        if (!isset($column)) {
            return true;
        }

        $conditions   = $params['conditions'];
        $alias        = ($params['alias']) ? $params['alias'].'.' : '';
        $conditions[] = [$alias.$column => $userid];

        $params['conditions'] = $conditions;

        $event->setParams($params);

        return true;
    }

    /**
     * @param RequestEvent $event
     *
     * @return bool
     */
    public function onDeleteRequest(RequestEvent $event)
    {
        $userid  = $this->app['userid'];
        $params  = $event->getParams();
        $details = $event->getDetails();

        if ($details['ignore'] === true || empty($userid)) {
            return true;
        }

        $tables = array_merge($this->tables['default'], $this->tables['delete']);
        $table  = str_replace('#__', '', $params['table']);

        $column = $tables[$table];
        if (!isset($column)) {
            return true;
        }

        $conditions   = $params['conditions'];
        $alias        = ($params['alias']) ? $params['alias'].'.' : '';
        $conditions[] = [$alias.$column => $userid];

        $params['conditions'] = $conditions;

        $event->setParams($params);

        return true;
    }

    /**
     * @param RequestEvent $event
     *
     * @return bool
     */
    public function onSaveRequest(RequestEvent $event)
    {
        $userid  = $this->app['userid'];
        $params  = $event->getParams();
        $details = $event->getDetails();

        if ($details['ignore'] === true || empty($userid)) {
            return true;
        }

        $tables = array_merge($this->tables['default'], $this->tables['save']);
        $table  = str_replace('#__', '', $params['table']);

        $column = $tables[$table];
        if (!isset($column)) {
            return true;
        }

        $alias = ($params['alias']) ? $params['alias'].'.' : '';

        foreach ($params['values'] as &$value) {
            $value[$alias.$column] = $userid;
        }

        $event->setParams($params);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            DatabaseEvents::BEFORE_FIND   => 'onFindRequest',
            DatabaseEvents::BEFORE_UPDATE => 'onUpdateRequest',
            DatabaseEvents::BEFORE_DELETE => 'onDeleteRequest',
            DatabaseEvents::BEFORE_SAVE   => 'onSaveRequest',
        ];
    }
}
