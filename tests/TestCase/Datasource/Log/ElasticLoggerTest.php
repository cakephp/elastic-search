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
namespace Cake\ElasticSearch\Test\TestCase\Datasource\Log;

use Cake\Database\Log\QueryLogger;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\ElasticSearch\TestSuite\TestCase;
use Cake\Log\Engine\ArrayLog;
use Psr\Log\LogLevel;

/**
 * Tests the ElasticLogger class with ArrayLog engine
 */
class ElasticLoggerTest extends TestCase
{
    /**
     * Extract JSON from formatted log message
     *
     * ArrayLog formats messages as "level: message", so we need to extract the JSON part
     *
     * @param string $formattedMessage The formatted log message
     * @return array<mixed> The decoded JSON
     */
    private function extractJsonFromLog(string $formattedMessage): array
    {
        // Remove the "level: " prefix
        $parts = explode(': ', $formattedMessage, 2);
        if (count($parts) === 2) {
            /** @var array<mixed>|null $decoded */
            $decoded = json_decode($parts[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Test that ArrayLog itself works
     */
    public function testArrayLogBasicFunctionality(): void
    {
        $arrayLog = new ArrayLog();
        $arrayLog->log(LogLevel::DEBUG, 'Test message');

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Test message', $logs[0]);
    }

    /**
     * Test ElasticLogger constructor
     */
    public function testConstruct(): void
    {
        $queryLogger = new QueryLogger();
        $connection = $this->getMockConnection();

        $elasticLogger = new ElasticLogger($queryLogger, $connection);

        $this->assertSame($queryLogger, $elasticLogger->getLogger());
        $this->assertInstanceOf(ElasticLogger::class, $elasticLogger);
    }

    /**
     * Test constructor with ArrayLog
     */
    public function testConstructWithArrayLog(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection();

        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $this->assertSame($arrayLog, $elasticLogger->getLogger());
    }

    /**
     * Test setLogger and getLogger methods
     */
    public function testSetAndGetLogger(): void
    {
        $initialLogger = new QueryLogger();
        $connection = $this->getMockConnection();
        $elasticLogger = new ElasticLogger($initialLogger, $connection);

        // Test initial logger
        $this->assertSame($initialLogger, $elasticLogger->getLogger());

        // Test setting new logger
        $newLogger = new ArrayLog();
        $result = $elasticLogger->setLogger($newLogger);

        $this->assertSame($elasticLogger, $result); // Test fluent interface
        $this->assertSame($newLogger, $elasticLogger->getLogger());
    }

    /**
     * Test log method when query logging is enabled with DEBUG level
     */
    public function testLogWhenLoggingEnabledDebugLevel(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 50,
                'from' => 0,
                'query' => ['match_all' => []],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertIsArray($data);
        $this->assertSame(50, $data['size']);
        $this->assertSame(0, $data['from']);
        $this->assertEquals(['match_all' => []], $data['query']);
    }

    /**
     * Test log method when query logging is disabled
     */
    public function testLogWhenLoggingDisabled(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(false);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 10,
                'query' => ['match_all' => []],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Test message', $context);

        $logs = $arrayLog->read();
        $this->assertEmpty($logs);
    }

    /**
     * Test that non-DEBUG levels are ignored
     */
    public function testNonDebugLevelsIgnored(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 20,
                'query' => ['match_all' => []],
            ],
        ];

        // Test various non-DEBUG levels
        $elasticLogger->log(LogLevel::INFO, 'Info message', $context);
        $elasticLogger->log(LogLevel::WARNING, 'Warning message', $context);
        $elasticLogger->log(LogLevel::ERROR, 'Error message', $context);

        $logs = $arrayLog->read();
        $this->assertEmpty($logs);
    }

    /**
     * Test logging without request context is ignored
     */
    public function testLogWithoutRequestIgnored(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $elasticLogger->log(LogLevel::DEBUG, 'Empty context', []);
        $elasticLogger->log(LogLevel::DEBUG, 'No request', ['response' => []]);

        $logs = $arrayLog->read();
        $this->assertEmpty($logs);
    }

    /**
     * Test search request with response - creates LoggedQuery
     */
    public function testSearchRequestWithResponse(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 50,
                'from' => 0,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['terms' => ['feed_id' => [112]]],
                        ],
                    ],
                ],
            ],
            'response' => [
                'took' => 4,
                'timed_out' => false,
                'hits' => [
                    'total' => [
                        'value' => 5443,
                        'relation' => 'eq',
                    ],
                    'max_score' => 0.0,
                    'hits' => [
                        ['_id' => '112-abc123', '_score' => 0.0],
                    ],
                ],
            ],
            'responseStatus' => 200,
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertSame(50, $data['size']);
        $this->assertSame(0, $data['from']);
        $this->assertIsArray($data['query']);
        $this->assertIsArray($data['query']['bool']);
    }

    /**
     * Test legacy hits.total format (direct number instead of object)
     */
    public function testLegacyHitsTotalFormat(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/legacy_index/_search',
                'data' => ['query' => ['match_all' => []]],
            ],
            'response' => [
                'took' => 5,
                'hits' => [
                    'total' => 42,
                    'hits' => [],
                ],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertIsArray($data);
    }

    /**
     * Test count operation response
     */
    public function testCountOperationLogging(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'query' => ['term' => ['status' => 'published']],
            ],
            'response' => [
                'count' => 87,
                '_shards' => [
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertIsArray($data);
        $this->assertIsArray($data['query']);
    }

    /**
     * Test request only (no response) logging
     */
    public function testRequestOnlyLogging(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 0,
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertSame(0, $data['size']);
    }

    /**
     * Test complex search query logging
     */
    public function testComplexSearchQuery(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 20,
                'from' => 40,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['title' => 'elasticsearch']],
                            ['range' => ['price' => ['gte' => 10]]],
                        ],
                        'filter' => [
                            ['term' => ['category' => 'books']],
                        ],
                    ],
                ],
            ],
            'response' => [
                'took' => 15,
                'timed_out' => false,
                'hits' => [
                    'total' => ['value' => 250, 'relation' => 'eq'],
                    'hits' => [],
                ],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertSame(20, $data['size']);
        $this->assertSame(40, $data['from']);
        $this->assertIsArray($data['query']['bool']['must']);
        $this->assertCount(2, $data['query']['bool']['must']);
    }

    /**
     * Test aggregation hits handling
     */
    public function testAggregationHitsHandling(): void
    {
        $arrayLog = new ArrayLog();
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($arrayLog, $connection);

        $context = [
            'request' => [
                'size' => 0,
                'query' => ['match_all' => []],
            ],
            'response' => [
                'took' => 8,
                'hits' => [
                    'total' => ['value' => 1000, 'relation' => 'eq'],
                    'hits' => [
                        ['_id' => '1', '_score' => 1.0],
                        ['_id' => '2', '_score' => 0.8],
                    ],
                ],
                'aggregations' => [
                    'categories' => [
                        'buckets' => [
                            ['key' => 'books', 'doc_count' => 500],
                            ['key' => 'electronics', 'doc_count' => 300],
                        ],
                    ],
                ],
            ],
        ];

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);

        $logs = $arrayLog->read();
        $this->assertCount(1, $logs);

        $data = $this->extractJsonFromLog($logs[0]);
        $this->assertSame(0, $data['size']);
        $this->assertIsArray($data['query']);
    }

    /**
     * Create a mock Connection instance
     */
    private function getMockConnection(bool $logQueries = true): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isQueryLoggingEnabled')->willReturn($logQueries);

        return $connection;
    }
}
