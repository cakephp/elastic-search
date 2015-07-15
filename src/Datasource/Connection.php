<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Datasource;

use Cake\Datasource\ConnectionInterface;
use Cake\Database\Log\LoggedQuery;
use Cake\ElasticSearch\Datasource\SchemaCollection;
use Elastica\Client;
use Elastica\Request;

class Connection extends Client implements ConnectionInterface
{
    /**
     * Whether or not query logging is enabled.
     *
     * @var bool
     */
    protected $logQueries = false;

    /**
     * The connection name in the connection manager.
     *
     * @var string
     */
    protected $configName = '';

    /**
     * Constructor. Appends the default index name to the config array, which by default
     * is `_all`
     *
     * @param array $config config options
     * @param callback $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        $config += ['index' => '_all'];
        if (isset($config['name'])) {
            $this->configName = $config['name'];
        }
        parent::__construct($config, $callback);
    }

    /**
     * Part of the implicit Connection interface.
     *
     * Returns a SchemaCollection stub until we can add more
     * abstract API's in Connection.
     *
     * @return bool
     */
    public function schemaCollection()
    {
        return new SchemaCollection($this);
    }

    /**
     * Part of the implicit Connection interface.
     *
     * @return bool
     */
    public function configName()
    {
        return $this->configName;
    }

    /**
     * Part of the implicit Connection interface.
     *
     * @return void
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->logQueries;
        }
        $this->logQueries = $enable;
    }

    /**
     * Part of the implicit Connection interface.
     *
     * @param bool $enable Whether or not to log queries
     * @return bool|void
     */
    public function transactional(callable $callable)
    {
        return $callable($this);
    }

    /**
     * Part of the implicit Connection interface.
     *
     * @param callable $callable An anonymous function to be called
     * @return callable The result of the called function
     */
    public function disableConstraints(callable $callable)
    {
        return $callable($this);
    }

    /**
     * Get the config data for this connection.
     *
     * @return array
     */
    public function config()
    {
        return $this->_config;
    }

    /**
     * Returns the index for the given connection
     *
     * @param  string $name Index name to create connection to, if no value is passed
     * it will use the default index name for the connection.
     * @return \Elastica\Index Index for the given name
     */
    public function getIndex($name = null)
    {
        return parent::getIndex($name ?: $this->getConfig('index'));
    }

    /**
     * Sets the logger object instance. When called with no arguments
     * it returns the currently setup logger instance.
     *
     * @param object $instance logger object instance
     * @return object logger instance
     */
    public function logger($instance = null)
    {
        if ($instance === null) {
            return $this->_logger;
        }
        $this->_logger = $instance;
    }

    /**
     * Log requests to Elastic Search.
     *
     * @param Request|array $context The context of the request made.
     * @return void
     */
    protected function _log($context)
    {
        if (!$this->logQueries) {
            return;
        }

        if ($context instanceof Request) {
            $data = $context->toArray();
        } else {
            $data = ['message' => $context];
        }
        $logData = [
            'method' => $data['method'],
            'path' => $data['path'],
            'data' => $data['data'],
        ];

        $data = json_encode($logData, JSON_PRETTY_PRINT);
        $loggedQuery = new LoggedQuery();
        $loggedQuery->query = $data;
        $this->_logger->log($loggedQuery);
    }
}
