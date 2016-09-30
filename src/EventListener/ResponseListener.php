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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class ResponseListener implements EventSubscriberInterface
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

    public function onFindResponse(ResponseEvent $event)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            DatabaseEvents::AFTER_FIND => 'onFindResponse',
        ];
    }
}
