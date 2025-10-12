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
 * @since         0.0.1
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\SchemaCollection;
use Cake\ElasticSearch\TestSuite\TestCase;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastica\Client;
use Elastica\Status;

/**
 * SchemaCollection test case
 */
class SchemaCollectionTest extends TestCase
{
    /**
     * Test constructor sets connection
     */
    public function testConstructor(): void
    {
        $connection = $this->createMock(Connection::class);
        $schemaCollection = new SchemaCollection($connection);

        $this->assertInstanceOf(SchemaCollection::class, $schemaCollection);
    }

    /**
     * Test listTables returns empty array when no indexes exist
     */
    public function testListTablesEmpty(): void
    {
        $status = $this->createMock(Status::class);
        $status->method('getIndexNames')->willReturn([]);

        $driver = $this->createMock(Client::class);
        $driver->method('getStatus')->willReturn($status);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($driver);

        $schemaCollection = new SchemaCollection($connection);
        $result = $schemaCollection->listTables();

        $this->assertSame([], $result);
    }

    /**
     * Test listTables returns index names when they exist
     */
    public function testListTablesWithIndexes(): void
    {
        $indexes = ['articles', 'users', 'comments'];

        $status = $this->createMock(Status::class);
        $status->method('getIndexNames')->willReturn($indexes);

        $driver = $this->createMock(Client::class);
        $driver->method('getStatus')->willReturn($status);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($driver);

        $schemaCollection = new SchemaCollection($connection);
        $result = $schemaCollection->listTables();

        $this->assertSame($indexes, $result);
    }

    /**
     * Test listTables handles ClientResponseException
     */
    public function testListTablesWithException(): void
    {
        $exception = $this->createMock(ClientResponseException::class);

        $driver = $this->createMock(Client::class);
        $driver->method('getStatus')->willThrowException($exception);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($driver);

        $schemaCollection = new SchemaCollection($connection);
        $result = $schemaCollection->listTables();

        $this->assertSame([], $result);
    }

    /**
     * Test listTables with mixed index names
     */
    public function testListTablesWithMixedIndexNames(): void
    {
        $indexes = ['test_articles', 'production_users', 'staging_comments', 'logs'];

        $status = $this->createMock(Status::class);
        $status->method('getIndexNames')->willReturn($indexes);

        $driver = $this->createMock(Client::class);
        $driver->method('getStatus')->willReturn($status);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDriver')->willReturn($driver);

        $schemaCollection = new SchemaCollection($connection);
        $result = $schemaCollection->listTables();

        $this->assertSame($indexes, $result);
        $this->assertCount(4, $result);
    }
}
