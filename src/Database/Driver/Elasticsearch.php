<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @internal      Should be used internal only - will be replaced with a real driver in
 * the future.
 * @since         2.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Database\Driver;

use Cake\Database\Driver;
use Cake\Database\Query;
use Cake\Database\ValueBinder;
use Elastica\Client as ElasticaClient;

/**
 * Represents a elasticsearch driver containing all specificities for
 * a elasticsearch engine including its DSL dialect.
 */
class Elasticsearch extends Driver
{
    /**
     * Base configuration settings for ElasticSearch driver
     *
     * @var array
     */
    protected $_baseConfig = [
        'host' => 'localhost',
        'username' => null,
        'password' => null,
        'port' => 9200,
    ];

    /**
     * Elastica client instance
     *
     * @var \Elastica\Client;
     */
    protected $_client;

    /**
     * Constructor.
     *
     * @param array $config config options
     * @param callable $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        $config += $this->_baseConfig;
        $this->_config = $config;

        $esLogger = $config['esLogger'];
        unset($config['esLogger']);

        $this->setConnection(new ElasticaClient($config, $callback, $esLogger));
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->_client = null;
    }

    /**
     * Get the internal Client instance.
     *
     * @return \Elastica\Client
     */
    public function getConnection()
    {
        return $this->_client;
    }

    /**
     * Set the internal Client instance.
     *
     * @param \Elastica\Client $client instance.
     * @return $this
     */
    public function setConnection($client)
    {
        $this->_client = $client;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function enabled()
    {
        return true;
    }

    /**
     * Just return the $query, as we haven't prepared statement
     * in ElasticSearch context.
     *
     * @param string|\Cake\ElasticSearch\Query $query The query.
     * @return string|\Cake\ElasticSearch\Query $query
     */
    public function prepare($query)
    {
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function releaseSavePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function savePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function rollbackSavePointSQL($name)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function disableForeignKeySQL()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function enableForeignKeySQL()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function supportsDynamicConstraints()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsSavePoints()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function quote($value, $type)
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function supportsQuoting()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function queryTranslator($type)
    {
        return function ($query) use ($type) {
            return $query;
        };
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function quoteIdentifier($identifier)
    {
        return $identifier;
    }

    /**
     * Escapes values for use in schema definitions.
     *
     * @param mixed $value The value to escape.
     * @return string String for use in schema definitions.
     */
    public function schemaValue($value)
    {
        return (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function schema()
    {
        return '';
    }

    /**
     * Returns last id generated for a index in elasticsearch.
     *
     * @param string|null $index index name to get last insert value from.
     * @param string|null $field the name of the field representing the primary key.
     * @return string|int
     */
    public function lastInsertId($index = null, $field = null)
    {
        $search = $this->getConnection()->getIndex($index)->search([
            'query' => [
                'match_all' => [],
            ],
            'sort' => [
                '_id' => [
                    'order' => 'desc',
                ],
            ],
            'size' => 1,
        ]);

        return $search->current()->_id;
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return $this->getConnection()->hasConnection();
    }

    /**
     * @inheritDoc
     */
    public function enableAutoQuoting($enable = true)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isAutoQuotingEnabled()
    {
        return false;
    }

    /**
     * Do nothing, as ElasticSearch don't need it.
     *
     * @param \Cake\Database\Query $query The query to compile.
     * @param \Cake\Database\ValueBinder $generator The value binder to use.
     * @return array .
     */
    public function compileQuery(Query $query, ValueBinder $generator)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function newCompiler()
    {
        return null;
    }
}
