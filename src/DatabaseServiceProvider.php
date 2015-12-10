<?php

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
            $database = new Database();

            $connection = $database->connect($this->getConfig());

            if (!$connection) {
                return $this->handleError();
            }

            $database->setContainer($di);

            register_shutdown_function(function () use ($database) {
                $database->disConnect();
            });

            return $database;
        };

        $di['db'] = function ($app) {
            return $app['database'];
        };
    }

    protected function getConfig($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        // To get the database connection configuration, we will just pull each of the
        // connection configurations and get the configurations for the given name.
        // If the configuration doesn't exist, we'll throw an exception and bail.
        $connections = ($this->app['database.connections']) ?: $this->app['config']['database.connections'];
        $config      = $connections[$name];

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
    public function getDefaultConnection()
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
