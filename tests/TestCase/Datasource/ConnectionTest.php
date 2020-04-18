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
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;

/**
 * Tests the connection class
 */
class ConnectionTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Log::drop('elasticsearch');
    }

    /**
     * Tests the getIndex method that calling it with no arguments,
     * which is not supported
     *
     * @return void
     */
    public function testGetEmptyIndex()
    {
        $this->expectException(\Elastica\Exception\InvalidException::class);

        $connection = new Connection();
        $connection->getIndex();
    }

    /**
     * Tests the getIndex method when defining a index name from different
     * ways
     *
     * @return void
     */
    public function testGetIndex()
    {
        $connection = new Connection();
        $index = $connection->getIndex('something_else,another');
        $this->assertEquals('something_else,another', $index->getName());

        $index = $connection->getIndex('baz');
        $this->assertEquals('baz', $index->getName());
    }

    /**
     * Ensure the log option works via the constructor
     *
     * @return void
     */
    public function testConstructLogOption()
    {
        $connection = new Connection();
        $this->assertFalse($connection->isQueryLoggingEnabled());

        $opts = ['log' => true];
        $connection = new Connection($opts);

        $this->assertTrue($connection->isQueryLoggingEnabled());
        $this->assertInstanceOf('\Cake\Log\Engine\FileLog', $connection->getLogger());
    }

    /**
     * Ensure that logging queries works.
     *
     * @return void
     */
    public function testQueryLoggingWithLog()
    {
        Log::setConfig('elasticsearch', [
            'engine' => 'Array',
        ]);

        $connection = ConnectionManager::get('test');
        $connection->enableQueryLogging();
        $result = $connection->request('_stats');
        $connection->disableQueryLogging(false);

        $this->assertNotEmpty($result);

        $logs = Log::engine('elasticsearch')->read();
        $this->assertCount(1, $logs);

        $message = json_encode([
            'method' => 'GET',
            'path' => '_stats',
            'data' => [],
        ], JSON_PRETTY_PRINT);
        $this->assertEquals('debug ' . $message, $logs[0]);
    }

    /**
     * Ensure that logging queries works.
     *
     * @return void
     */
    public function testLoggerQueryLogger()
    {
        Log::setConfig('elasticsearch', [
            'engine' => 'Array',
        ]);
        $logger = new QueryLogger();

        $query = new LoggedQuery();
        $query->query = json_encode([
            'method' => 'GET',
            'path' => '_stats',
            'data' => [],
        ], JSON_PRETTY_PRINT);

        $connection = ConnectionManager::get('test');
        $connection->setLogger($logger);
        $connection->enableQueryLogging();
        $result = $connection->request('_stats');
        $connection->disableQueryLogging();

        $logs = Log::engine('elasticsearch')->read();
        $this->assertCount(1, $logs);
        $this->assertStringStartsWith('debug ', $logs[0]);
        $this->assertStringContainsString('duration=', $logs[0]);
        $this->assertStringContainsString('rows=', $logs[0]);
    }
}
