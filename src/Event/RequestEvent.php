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
class RequestEvent extends Event
{
    /**
     * Save key value pairs.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Additional Details.
     *
     * @var array
     */
    protected $details = [];

    public function __construct($params, $details = [])
    {
        $this->params  = $params;
        $this->details = $details;
    }

    /**
     * Return the table name on which query will run.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->params['table'];
    }

    /**
     * Returns the data to be saved.
     *
     * @return array
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Returns the additional params passed.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Results to send in case of propagation stopped.
     *
     * @return array
     */
    public function getResults()
    {
        return [];
    }

    /**
     * Set the Request params.
     *
     * @param array $params
     */
    public function setParams($params = [])
    {
        $this->params = $params;
    }

    /**
     * Set the Request details.
     *
     * @param array $params
     */
    public function setDetails($details = [])
    {
        $this->details = $details;
    }
}
