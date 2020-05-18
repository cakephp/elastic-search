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

use Cake\Core\App;
use Cake\Core\Exception\Exception;
use Cake\Database\Exception\MissingDriverException;
use Cake\Datasource\ConnectionInterface;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\Log\Log;
use Elastica\Client as ElasticaClient;
use Elastica\Log as ElasticaLog;
use Elastica\Request;
use Psr\Log\LoggerInterface;

class Connection implements ConnectionInterface
{
    /**
     * Contains the configuration params for this connection.
     *
     * @var array
     */
    protected $_config;

    /**
     * Driver object, responsible for creating the real connection.
     *
     * @var \Cake\ElasticSearch\Database\Driver
     */
    protected $_driver;

    /**
     * Whether to log queries generated during this connection.
     *
     * @var bool
     */
    protected $_logQueries = false;

    /**
     * Logger object instance.
     *
     * @var \Cake\Database\Log\QueryLogger|null
     */
    protected $_logger;

    /**
     * Instance of ElasticLogger
     * @var \Cake\ElasticSearch\Datasource\Log\ElasticLogger
     */
    protected $_esLogger;

    /**
     * The schema collection object
     *
     * @var \Cake\Database\Schema\Collection|null
     */
    protected $_schemaCollection;

    /**
     * Constructor.
     *
     * @param array $config config options
     * @param callable $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        $this->_config = $config;

        if (!empty($config['esLogger'])) {
            if (!($config['esLogger'] instanceof LoggerInterface)) {
                throw new Exception("Value of 'esLogger' must implement \Psr\Log\LoggerInterface");
            }

            $this->_esLogger = $config['esLogger'];
        }

        $driver = '';
        if (!empty($config['driver'])) {
            $driver = $config['driver'];
        }
        $this->setDriver($driver, $config);

        if (!empty($config['log'])) {
            $this->enableQueryLogging($config['log']);
        }
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
        $client = $this->getDriver()->getConnection();
        if (method_exists($client, $name)) {
            return call_user_func_array([$client, $name], $attributes);
        }
    }

    /**
     * @inheritDoc
     */
    public function config()
    {
        return $this->_config;
    }

    /**
     * @inheritDoc
     */
    public function configName()
    {
        if (empty($this->_config['name'])) {
            return '';
        }

        return $this->_config['name'];
    }

    /**
     * Sets the driver instance. If a string is passed it will be treated
     * as a class name and will be instantiated.
     *
     * @param \Cake\ElasticSearch\Database\Driver|string $driver The driver instance to use.
     * @param array $config Config for a new driver.
     * @throws \Cake\Database\Exception\MissingDriverException When a driver class is missing.
     * @return $this
     */
    public function setDriver($driver, $config = [])
    {
        if (is_string($driver)) {
            $className = App::className($driver, 'Database/Driver');
            if (!$className || !class_exists($className)) {
                throw new MissingDriverException(['driver' => $driver]);
            }

            $config['esLogger'] = $this->getEsLogger();
            $callback = $config['callback'] ?? null;
            $driver = new $className($config, $callback);
        }

        $this->_driver = $driver;

        return $this;
    }

    /**
     * Gets the driver instance.
     *
     * @return \Cake\Database\Driver
     */
    public function getDriver()
    {
        return $this->_driver;
    }

    /**
     * Sets a Schema\Collection object for this connection.
     *
     * @param \Cake\ElasticSearch\Datasource\SchemaCollection $collection The schema collection object
     * @return $this
     */
    public function setSchemaCollection(SchemaCollection $collection)
    {
        $this->_schemaCollection = $collection;

        return $this;
    }

    /**
     * Returns a SchemaCollection stub until we can add more
     * abstract API's in Connection.
     *
     * @return \Cake\ElasticSearch\Datasource\SchemaCollection
     */
    public function getSchemaCollection()
    {
        if ($this->_schemaCollection !== null) {
            return $this->_schemaCollection;
        }

        return $this->_schemaCollection = new SchemaCollection($this);
    }

    /**
     * @inheritDoc
     */
    public function enableQueryLogging($value)
    {
        $this->_logQueries = (bool)$value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->_logQueries;
        }

        $this->_logQueries = $enable;
    }

    /**
     * @inheritDoc
     */
    public function transactional(callable $transaction)
    {
        return $transaction($this);
    }

    /**
     * @inheritDoc
     */
    public function disableConstraints(callable $operation)
    {
        return $operation($this);
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

        if ($this->_esLogger) {
            $this->getEsLogger()->setLogger($logger);
        }

        return $this;
    }

    /**
     * Get the logger object
     * Will set the default logger to elasticsearch if found, or debug
     * If none of the above are found the default Es logger will be used.
     *
     * @return \Cake\Database\Log\QueryLogger logger instance
     */
    public function getLogger()
    {
        if ($this->_logger === null) {
            $engine = Log::engine('elasticsearch') ?: Log::engine('debug');

            if (!$engine) {
                $engine = new ElasticaLog();
            }

            $this->_logger = $engine;
        }

        return $this->_logger;
    }

    /**
     * @inheritDoc
     */
    public function logger($instance = null)
    {
        deprecationWarning(
            'Connection::logger() is deprecated. ' .
            'Use Connection::setLogger()/getLogger() instead.'
        );

        if ($instance === null) {
            return $this->getLogger();
        }

        $this->setLogger($instance);
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
}
