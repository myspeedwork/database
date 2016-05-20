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

use InvalidArgumentException;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

/**
 * Speedwork database service Provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(Container $di)
    {
        $di['database'] = function ($di) {
            return $this->getConnection();
        };

        $di['db'] = function ($app) {
            return $app['database'];
        };
    }

    protected function getConnection($name = 'default')
    {
        $config = $this->getConfig($name);

        $wrapperClass = $config['wrapper'] ?: 'Database';

        $connection = new $wrapperClass($config);
        $connection->connect();

        if (!$connection->isConnected()) {
            return $this->handleError();
        }

        $connection->setContainer($this->app);

        register_shutdown_function(function () use ($connection) {
            $connection->disConnect();
        });

        return $connection;
    }

    protected function getConfig($name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        // To get the database connection configuration, we will just pull each of the
        // connection configurations and get the configurations for the given name.
        // If the configuration doesn't exist, we'll throw an exception and bail.
        $connections = ($this->app['database.connections']) ?: $this->app['config']['database.connections'];

        $config = $connections[$name];

        if (is_null($config)) {
            throw new InvalidArgumentException("Database [$name] not configured.");
        }

        return $config;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    protected function getDefaultConnection()
    {
        return ($this->app['database.default']) ?: $this->app['config']['database.default'];
    }

    protected function handleError()
    {
        if (php_sapi_name() == 'cli' || $this->app['is_api_request']) {
            echo json_encode([
                'status'  => 'ERROR',
                'message' => 'database was gone away',
            ]);
        } else {
            $path = SYS.'public'.DS.'templates'.DS.'system'.DS.'databasegone.tpl';
            echo file_get_contents($path);
            echo '<!-- Database was gone away... -->';
        }
        exit;
    }
}
