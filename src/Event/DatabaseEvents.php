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

/**
 * Contains all events thrown in the Database component.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
final class DatabaseEvents
{
    /**
     * The BEFORE_SAVE event occurs before building the query.
     *
     * This event allows you to change the table names and data
     *
     * @var string
     */
    const BEFORE_SAVE = 'database.before_save';

    /**
     * The AFTER_SAVE event occurs after executing the query.
     *
     * This event allows you to change the results of the query
     *
     * @var string
     */
    const AFTER_SAVE = 'database.after_save';

    /**
     * The BEFORE_FIND event occurs before building the query.
     *
     * This event allows you to change the table names and data
     *
     * @var string
     */
    const BEFORE_FIND = 'database.before_find';

    /**
     * The AFTER_FIND event occurs after executing the query.
     *
     * This event allows you to change the results of the query
     *
     * @var string
     */
    const AFTER_FIND = 'database.after_find';

    /**
     * The BEFORE_DELETE event occurs before building the query.
     *
     * This event allows you to change the table names and data
     *
     * @var string
     */
    const BEFORE_DELETE = 'database.before_delete';

    /**
     * The AFTER_DELETE event occurs after executing the query.
     *
     * This event allows you to change the results of the query
     *
     * @var string
     */
    const AFTER_DELETE = 'database.after_delete';

    /**
     * The BEFORE_UPDATE event occurs before building the query.
     *
     * This event allows you to change the table names and data
     *
     * @var string
     */
    const BEFORE_UPDATE = 'database.before_update';

    /**
     * The AFTER_UPDATE event occurs after executing the query.
     *
     * This event allows you to change the results of the query
     *
     * @var string
     */
    const AFTER_UPDATE = 'database.after_update';
}
