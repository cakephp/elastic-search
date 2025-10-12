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

use Cake\Cache\Cache;
use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\ElasticSearch\Datasource\SchemaCollection;
use Cake\ElasticSearch\Exception\NotImplementedException;
use Cake\ElasticSearch\TestSuite\TestCase;
use Cake\Log\Log;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastica\Client;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConnectionTest extends TestCase
{
    /**
     * Get test configuration for Connection
     *
     * @param array $additional Additional config options to merge
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

    /**
     * Test __call method delegation to Elastica client
     */
    public function testCallMethodDelegation(): void
    {
        $connection = new Connection($this->getTestConfig());

        // Test successful delegation - info() method exists on Elastica client
        $result = $connection->info();
        $this->assertIsObject($result);
        $this->assertInstanceOf(Elasticsearch::class, $result);

        // Test method that doesn't exist should throw NotImplementedException
        $this->expectException(NotImplementedException::class);
        $connection->nonExistentMethod();
    }

    /**
     * Test getSchemaCollection method
     */
    public function testGetSchemaCollection(): void
    {
        $connection = new Connection($this->getTestConfig());
        $schemaCollection = $connection->getSchemaCollection();

        $this->assertInstanceOf(SchemaCollection::class, $schemaCollection);
    }

    /**
     * Test disableConstraints method
     */
    public function testDisableConstraints(): void
    {
        $connection = new Connection($this->getTestConfig());

        $called = false;
        $result = $connection->disableConstraints(function ($conn) use (&$called, $connection) {
            $called = true;
            $this->assertSame($connection, $conn);

            return 'test result';
        });

        $this->assertTrue($called);
        $this->assertSame('test result', $result);
    }

    /**
     * Test cache management methods
     */
    public function testCacheManagement(): void
    {
        // Set up cache configuration for testing
        Cache::setConfig('_cake_model_', [
            'className' => 'File',
            'path' => sys_get_temp_dir(),
        ]);

        $connection = new Connection($this->getTestConfig());

        // Test default cacher
        $defaultCacher = $connection->getCacher();
        $this->assertInstanceOf(CacheInterface::class, $defaultCacher);

        // Test setting custom cacher
        $mockCache = $this->createMock(CacheInterface::class);
        $connection->setCacher($mockCache);

        $this->assertSame($mockCache, $connection->getCacher());

        // Clean up
        Cache::drop('_cake_model_');
    }

    /**
     * Test getDriver method
     */
    public function testGetDriver(): void
    {
        $connection = new Connection($this->getTestConfig());

        $driver = $connection->getDriver();
        $this->assertInstanceOf(Client::class, $driver);

        // Test with role parameter (should return same client)
        $driverWithRole = $connection->getDriver(Connection::ROLE_WRITE);
        $this->assertSame($driver, $driverWithRole);
    }

    /**
     * Test constructor with legacy configuration (should convert automatically)
     */
    public function testConstructorLegacyConfigConversion(): void
    {
        // Test with legacy host/port configuration - should convert to hosts array
        $connection = new Connection([
            'host' => '127.0.0.1',
            'port' => 9200,
        ]);

        $config = $connection->config();
        $this->assertArrayHasKey('hosts', $config);
        $this->assertEquals(['127.0.0.1:9200'], $config['hosts']);
        $this->assertArrayNotHasKey('host', $config);
        $this->assertArrayNotHasKey('port', $config);
    }

    /**
     * Test different configuration variations
     */
    public function testConfigurationVariations(): void
    {
        // Test with minimal valid configuration
        $connection = new Connection(['hosts' => ['127.0.0.1:9200']]);
        $this->assertIsArray($connection->config());
        $this->assertEquals(['127.0.0.1:9200'], $connection->config()['hosts']);

        // Test with additional configuration options
        $config = [
            'hosts' => ['127.0.0.1:9200'],
            'log' => true,
            'index' => 'test_index',
        ];
        $connection = new Connection($config);
        $this->assertEquals($config, $connection->config());
    }

    /**
     * Test configName method
     */
    public function testConfigName(): void
    {
        $connection = new Connection($this->getTestConfig(['name' => 'test_connection']));
        $this->assertEquals('test_connection', $connection->configName());

        // Test with default empty name
        $connection = new Connection($this->getTestConfig());
        $this->assertEquals('', $connection->configName());
    }

    /**
     * Test query logging enable/disable methods
     */
    public function testQueryLoggingToggle(): void
    {
        $connection = new Connection($this->getTestConfig());

        // Test initial state
        $this->assertFalse($connection->isQueryLoggingEnabled());

        // Test enabling
        $result = $connection->enableQueryLogging();
        $this->assertSame($connection, $result); // Test fluent interface
        $this->assertTrue($connection->isQueryLoggingEnabled());

        // Test disabling
        $result = $connection->disableQueryLogging();
        $this->assertSame($connection, $result); // Test fluent interface
        $this->assertFalse($connection->isQueryLoggingEnabled());

        // Test enabling with explicit parameter
        $connection->enableQueryLogging(false);
        $this->assertFalse($connection->isQueryLoggingEnabled());
    }
}
