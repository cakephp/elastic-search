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

use Elastica\Exception\ResponseException;

/**
 * Temporary shim for fixtures as they know too much about databases.
 */
class SchemaCollection
{
    /**
     * The connection instance to use.
     *
     * @var \Cake\ElasticSearch\Datasource\Connection
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $connection The connection instance to use.
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns an empty array as a shim for fixtures
     *
     * @return array An empty array
     */
    public function listTables()
    {
        try {
            $index = $this->connection->getIndex();
            $mappings = $index->getMapping();
        } catch (ResponseException $e) {
            return [];
        }

        return array_keys($mappings);
    }
}
