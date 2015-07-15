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

use Cake\ElasticSearch\Datasource\Connection;

/**
 * Temporary shim for fixtures as they know too much about databases.
 */
class SchemaCollection
{
    /**
     * The connection this schema collection is for.
     *
     * @var \Cake\ElasticSearch\Datasource\Connection
     */
    protected $connection;

    /**
     * Constructor.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection The connection to use.
     */
    public function __construct(Connection $connection)
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
        $index = $this->connection->getIndex();
        $result = $index->getMapping();
        return array_keys($result);
    }
}
