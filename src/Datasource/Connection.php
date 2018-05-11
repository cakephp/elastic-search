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

use Cake\Database\Log\LoggedQuery;
use Cake\Datasource\ConnectionInterface;
use Cake\Log\Log;
use Elastica\Client;
use Elastica\Log as ElasticaLog;
use Elastica\Request;
use Psr\Log\NullLogger;

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
     * Constructor.
     *
     * @param array $config config options
     * @param callable $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        if (isset($config['name'])) {
            $this->configName = $config['name'];
        }
        if (isset($config['log'])) {
            $this->logQueries((bool)$config['log']);
        }
        parent::__construct($config, $callback);
    }

    /**
     * Returns a SchemaCollection stub until we can add more
     * abstract API's in Connection.
     *
     * @return \Cake\ElasticSearch\Datasource\SchemaCollection
     */
    public function schemaCollection()
    {
        return new SchemaCollection($this);
    }

    /**
     * {@inheritDoc}
     */
    public function configName()
    {
        return $this->configName;
    }

    /**
     * {@inheritDoc}
     */
    public function enabled()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function disableForeignKeys()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function enableForeignKeys()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->logQueries;
        }
        $this->logQueries = $enable;
    }

    /**
     * {@inheritDoc}
     */
    public function transactional(callable $callable)
    {
        return $callable($this);
    }

    /**
     * {@inheritDoc}
     *
     * Elasticsearch does not deal with the concept of foreign key constraints
     * This method just triggers the $callback argument.
     */
    public function disableConstraints(callable $callback)
    {
        return $callback($this);
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
     * Sets the logger object instance. When called with no arguments
     * it returns the currently setup logger instance.
     *
     * @param object $instance logger object instance
     * @return object logger instance
     * @deprecated 2.0 Will be replaced by getLogger()/setLogger()
     */
    public function logger($instance = null)
    {
        if ($instance === null) {
            return $this->_logger;
        }
        $this->_logger = $instance;
    }

    /**
     * Sets the logger object instance.
     *
     * @param object $instance logger object instance
     * @return void
     */
    public function setLogger($instance)
    {
        $this->_logger = $instance;
    }

    /**
     * Get the logger object instance.
     *
     * @return object logger instance
     */
    public function getLogger()
    {
        return $this->_logger;
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

        if (!isset($this->_logger) || $this->_logger instanceof NullLogger) {
            $this->_logger = Log::engine('elasticsearch') ?: new ElasticaLog();
        }

        if ($context instanceof Request) {
            $contextArray = $context->toArray();
            $logData = [
                'method' => $contextArray['method'],
                'path' => $contextArray['path'],
                'data' => $contextArray['data']
            ];
        } elseif ($context instanceof \Exception) {
            $logData = ['message' => $context->getMessage()];
        } else {
            $logData = ['message' => 'Unknown'];
        }

        $data = json_encode($logData, JSON_PRETTY_PRINT);
        $loggedQuery = new LoggedQuery();
        $loggedQuery->query = $data;

        if ($this->_logger instanceof \Psr\Log\LoggerInterface) {
            $this->_logger->log('debug', $loggedQuery);
        } else {
            $this->_logger->log($loggedQuery);
        }
    }
}
