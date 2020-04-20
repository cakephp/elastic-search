<?php
declare(strict_types=1);

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

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionInterface;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\ElasticSearch\Exception\NotImplementedException;
use Cake\Log\Log;
use Elastica\Client as ElasticaClient;
use Elastica\Log as ElasticaLog;
use Elastica\Query\BoolQuery;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

class Connection implements ConnectionInterface
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
     * Elastica client instance
     *
     * @var \Elastica\Client;
     */
    protected $_client;

    /**
     * Logger object instance.
     *
     * @var \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Instance of ElasticLogger
     *
     * @var \Cake\ElasticSearch\Datasource\Log\ElasticLogger
     */
    protected $_esLogger;

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
            $this->enableQueryLogging((bool)$config['log']);
        }

        $this->_client = new ElasticaClient($config, $callback, $this->getEsLogger());
    }

    /**
     * Pass remaining methods to the elastica client (if they exist)
     * And set the current logger based on current logQueries value
     *
     * @param string $name Method name
     * @param array $attributes Method attributes
     * @return mixed
     */
    public function __call($name, $attributes)
    {
        if (method_exists($this->_client, $name)) {
            return call_user_func_array([$this->_client, $name], $attributes);
        }
    }

    /**
     * Returns a SchemaCollection stub until we can add more
     * abstract API's in Connection.
     *
     * @return \Cake\ElasticSearch\Datasource\SchemaCollection
     */
    public function getSchemaCollection()
    {
        return new SchemaCollection($this);
    }

    /**
     * @inheritDoc
     */
    public function configName(): string
    {
        return $this->configName;
    }

    /**
     * @inheritDoc
     */
    public function enabled()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
    }

    /**
     * @inheritDoc
     */
    public function disableForeignKeys()
    {
    }

    /**
     * @inheritDoc
     */
    public function enableForeignKeys()
    {
    }

    /**
     * Enable/disable query logging
     *
     * @param bool $value Enable/disable query logging
     * @return $this
     */
    public function enableQueryLogging(bool $value = true)
    {
        $this->logQueries = $value;

        return $this;
    }

    /**
     * Disable query logging
     *
     * @return $this
     */
    public function disableQueryLogging()
    {
        $this->logQueries = false;

        return $this;
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled(): bool
    {
        return $this->logQueries;
    }

    /**
     * @inheritDoc
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
     *
     * @param callable $operation The callback to execute within a transaction.
     * @return mixed The return value of the callback.
     * @throws \Exception Will re-throw any exception raised in $callback after
     *   rolling back the transaction.
     */
    public function disableConstraints(callable $operation)
    {
        return $operation($this);
    }

    /**
     * Get the config data for this connection.
     *
     * @return array
     */
    public function config(): array
    {
        return $this->_client->getConfig();
    }

    /**
     * Sets a logger
     *
     * @param \Cake\Database\Log\QueryLogger|\Cake\Log\Engine\BaseLog $logger Logger instance
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;
        $this->getEsLogger()->setLogger($logger);

        return $this;
    }

    /**
     * Get the logger object
     * Will set the default logger to elasticsearch if found, or debug
     * If none of the above are found the default Es logger will be used.
     *
     * @return \Psr\Log\LoggerInterface logger instance
     */
    public function getLogger(): LoggerInterface
    {
        if ($this->_logger === null) {
            $engine = Log::engine('elasticsearch') ?: Log::engine('debug');

            if (!$engine) {
                $engine = new ElasticaLog();
            }

            $this->setLogger($engine);
        }

        return $this->_logger;
    }

    /**
     * Return instance of ElasticLogger
     *
     * @return \Cake\ElasticSearch\Datasource\Log\ElasticLogger
     */
    public function getEsLogger()
    {
        if ($this->_esLogger === null) {
            $this->_esLogger = new ElasticLogger($this->getLogger(), $this);
        }

        return $this->_esLogger;
    }

    /**
     * @inheritDoc
     */
    public function setCacher(CacheInterface $cacher)
    {
        $this->cacher = $cacher;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCacher(): CacheInterface
    {
        if ($this->cacher !== null) {
            return $this->cacher;
        }

        $configName = $this->_config['cacheMetadata'] ?? '_cake_model_';
        if (!is_string($configName)) {
            $configName = '_cake_model_';
        }

        if (!class_exists(Cache::class)) {
            throw new RuntimeException(
                'To use caching you must either set a cacher using Connection::setCacher()' .
                ' or require the cakephp/cache package in your composer config.'
            );
        }

        return $this->cacher = Cache::pool($configName);
    }

    /**
     * @inheritDoc
     */
    public function execute($query, $params = [], array $types = [])
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function prepare($sql)
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @see \Cake\Datasource\ConnectionInterface::getDriver()
     * @return \Elastica\Client
     */
    public function getDriver()
    {
        return $this->_client;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Cake\Datasource\ConnectionInterface::supportsDynamicConstraints()
     * @return bool
     */
    public function supportsDynamicConstraints()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Cake\Datasource\ConnectionInterface::newQuery()
     * @return \Elastica\Query\BoolQuery
     */
    public function newQuery()
    {
        return new BoolQuery();
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
        return $this->_client->getIndex($name ?: $this->getConfig('index'));
    }
}
