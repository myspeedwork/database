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
    public function register(Container $app)
    {
        $app['database'] = function ($app) {
            return $this->getConnection();
        };

        $app['db'] = function ($app) {
            return $app['database'];
        };
    }

    protected function getConnection($name = null)
    {
        $config = $this->getConfig($name);

        $wrapperClass = $config['wrapper'] ?: '\\Speedwork\\Database\\Database';
        $helpers      = $this->getSettings('database.helpers');

        $connection = new $wrapperClass();
        $connection->setContainer($this->app);
        $connection->setConfig($config);
        $connection->setHelpers($helpers);
        $connection->connect();

        if (!$connection->isConnected()) {
            return $this->handleError();
        }

        register_shutdown_function(
            function () use ($connection) {
                $connection->disConnect();
            }
        );

        return $connection;
    }

    protected function getConfig($name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        // To get the database connection configuration, we will just pull each of the
        // connection configurations and get the configurations for the given name.
        // If the configuration doesn't exist, we'll throw an exception and bail.
        $connections = $this->getSettings('database.connections');

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
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 'ERROR',
                'message' => 'database was gone away',
            ]);
        } else {
            $path = THEMES.'system'.DS.'dbgone.tpl';
            echo file_get_contents($path);
            echo '<!-- Database was gone away... -->';
        }
        exit;
    }
}
