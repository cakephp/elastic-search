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

use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\ElasticSearch\TestSuite\TestCase;
use Cake\Log\Log;
use Psr\Log\LoggerInterface;

class ConnectionTest extends TestCase
{
    /**
     * Get test configuration for Connection
     *
     * @param array $additional Additional config options to merge
     * @return array
     */
    protected function getTestConfig(array $additional = []): array
    {
        $testConnection = ConnectionManager::get('test');
        $baseConfig = $testConnection->config();

        return array_merge($baseConfig, $additional);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Log::drop('elasticsearch');
    }

    /**
     * Tests the getIndex method when defining a index name from different
     * ways
     */
    public function testGetIndex(): void
    {
        $connection = new Connection();
        $index = $connection->getIndex('something_else,another');
        $this->assertSame('something_else,another', $index->getName());

        $index = $connection->getIndex('baz');
        $this->assertSame('baz', $index->getName());
    }

    /**
     * Ensure the log option works via the constructor
     */
    public function testConstructLogOption(): void
    {
        $connection = new Connection($this->getTestConfig());
        $this->assertFalse($connection->isQueryLoggingEnabled());

        $opts = $this->getTestConfig(['log' => true]);
        $connection = new Connection($opts);

        $this->assertTrue($connection->isQueryLoggingEnabled());
        $this->assertInstanceOf(LoggerInterface::class, $connection->getLogger());
    }

    /**
     * Ensure that logging queries works with Elastica 9.x PSR-3 logging.
     */
    public function testQueryLoggingWithLog(): void
    {
        // Create a connection with logging enabled using modern Elastica 9.x config
        $connection = ConnectionManager::get('test');
        $connection->enableQueryLogging();
        $this->assertTrue($connection->isQueryLoggingEnabled());

        // Get the ElasticLogger - it should be created automatically with logging enabled
        $elasticLogger = $connection->getEsLogger();
        $this->assertInstanceOf(ElasticLogger::class, $elasticLogger);

        // Test that the ElasticLogger can log messages (basic PSR-3 functionality)
        $elasticLogger->info('Test log message', ['test' => true]);
        $elasticLogger->debug('Debug message', ['operation' => 'test']);

        // Verify ElasticLogger implements PSR-3 LoggerInterface correctly
        $this->assertInstanceOf(LoggerInterface::class, $elasticLogger);
    }

    /**
     * Ensure that QueryLogger integration works with Elastica 9.x.
     */
    public function testLoggerQueryLogger(): void
    {
        $logger = new QueryLogger();

        // Create connection and set QueryLogger
        $connection = ConnectionManager::get('test');
        $connection->setLogger($logger);

        // Verify logger was set correctly
        $this->assertSame($logger, $connection->getLogger());

        // Enable logging and verify it works
        $connection->enableQueryLogging();
        $this->assertTrue($connection->isQueryLoggingEnabled());

        // Verify ElasticLogger was configured with the QueryLogger
        $elasticLogger = $connection->getEsLogger();
        $this->assertSame($logger, $elasticLogger->getLogger());
    }
}
