<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Database\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Base class for events thrown in the Database component.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class ResponseEvent extends Event
{
    /**
     * Database Table name.
     *
     * @var string
     */
    protected $table;

    /**
     * Save key value pairs.
     *
     * @var array
     */
    protected $results = [];

    /**
     * Additional Details.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Database Query that got executed.
     *
     * @var string
     */
    protected $query;

    public function __construct(RequestEvent $request, $results = [], $query = null)
    {
        $this->request = $request;
        $this->results = $results;
        $this->query   = $query;
    }

    /**
     * Return the RequestEvent Object.
     *
     * @return \Speedwork\Database\Event\RequestEvent
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Return the table name on which query will run.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->request->getTable();
    }

    /**
     * Returns the additional params passed.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->request->getParams();
    }

    /**
     * Results to send in case of propagation stopped.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Returns the database Query.
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }
}
