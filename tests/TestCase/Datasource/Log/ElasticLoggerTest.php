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

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\Log\ElasticLogger;
use Cake\ElasticSearch\TestSuite\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Tests the ElasticLogger class with simple array format
 */
class ElasticLoggerTest extends TestCase
{
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
     * Test constructor with PSR-3 LoggerInterface
     */
    public function testConstructWithPsrLogger(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection();

        $elasticLogger = new ElasticLogger($psrLogger, $connection);

        $this->assertSame($psrLogger, $elasticLogger->getLogger());
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
        $newLogger = $this->createMock(LoggerInterface::class);
        $result = $elasticLogger->setLogger($newLogger);

        $this->assertSame($elasticLogger, $result); // Test fluent interface
        $this->assertSame($newLogger, $elasticLogger->getLogger());
    }

    /**
     * Test log method when query logging is enabled with DEBUG level
     */
    public function testLogWhenLoggingEnabledDebugLevel(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'size' => 50,
                'from' => 0,
                'query' => ['match_all' => []],
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['size']) && $data['size'] === 50 &&
                           isset($data['from']) && $data['from'] === 0 &&
                           isset($data['query']['match_all']);
                }),
                $this->anything(),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test log method when query logging is disabled
     */
    public function testLogWhenLoggingDisabled(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(false);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'size' => 10,
                'query' => ['match_all' => []],
            ],
        ];

        $mockLogger->expects($this->never())
            ->method('log');

        $elasticLogger->log(LogLevel::DEBUG, 'Test message', $context);
    }

    /**
     * Test that non-DEBUG levels are ignored
     */
    public function testNonDebugLevelsIgnored(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'size' => 20,
                'query' => ['match_all' => []],
            ],
        ];

        $mockLogger->expects($this->never())
            ->method('log');

        // Test various non-DEBUG levels
        $elasticLogger->log(LogLevel::INFO, 'Info message', $context);
        $elasticLogger->log(LogLevel::WARNING, 'Warning message', $context);
        $elasticLogger->log(LogLevel::ERROR, 'Error message', $context);
    }

    /**
     * Test logging without request context is ignored
     */
    public function testLogWithoutRequestIgnored(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $mockLogger->expects($this->never())
            ->method('log');

        $elasticLogger->log(LogLevel::DEBUG, 'Empty context', []);
        $elasticLogger->log(LogLevel::DEBUG, 'No request', ['response' => []]);
    }

    /**
     * Test search request with response - creates LoggedQuery
     */
    public function testSearchRequestWithResponse(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

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

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['size']) && $data['size'] === 50 &&
                           isset($data['query']['bool']['filter']) &&
                           $data['from'] === 0;
                }),
                $this->callback(function ($logContext) {
                    if (!isset($logContext['query']) || !($logContext['query'] instanceof LoggedQuery)) {
                        return false;
                    }

                    $queryContext = $logContext['query']->getContext();

                    return isset($queryContext['took']) && $queryContext['took'] === 4.0 &&
                           isset($queryContext['numRows']) && $queryContext['numRows'] === 5443;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test legacy hits.total format (direct number instead of object)
     */
    public function testLegacyHitsTotalFormat(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/legacy_index/_search',
                'data' => ['query' => ['match_all' => []]],
            ],
            'response' => [
                'took' => 5,
                'hits' => [
                    'total' => 42, // Legacy format - direct number
                    'hits' => [],
                ],
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->anything(),
                $this->callback(function ($logContext) {
                    if (!isset($logContext['query']) || !($logContext['query'] instanceof LoggedQuery)) {
                        return false;
                    }

                    $queryContext = $logContext['query']->getContext();

                    return isset($queryContext['numRows']) && $queryContext['numRows'] === 42 &&
                           isset($queryContext['took']) && $queryContext['took'] === 5.0;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test count operation response
     */
    public function testCountOperationLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

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

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->anything(),
                $this->callback(function ($logContext) {
                    if (!isset($logContext['query']) || !($logContext['query'] instanceof LoggedQuery)) {
                        return false;
                    }

                    $queryContext = $logContext['query']->getContext();

                    return isset($queryContext['numRows']) && $queryContext['numRows'] === 87;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test request only (no response) logging
     */
    public function testRequestOnlyLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'size' => 0,
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['size']) && $data['size'] === 0;
                }),
                $this->callback(function ($logContext) {
                    // Should not create LoggedQuery when no response
                    return !isset($logContext['query']);
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test complex search query logging
     */
    public function testComplexSearchQuery(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

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

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['size']) && $data['size'] === 20 &&
                           isset($data['from']) && $data['from'] === 40 &&
                           isset($data['query']['bool']['must']);
                }),
                $this->callback(function ($logContext) {
                    if (!isset($logContext['query']) || !($logContext['query'] instanceof LoggedQuery)) {
                        return false;
                    }

                    $queryContext = $logContext['query']->getContext();

                    return isset($queryContext['took']) && $queryContext['took'] === 15.0 &&
                           isset($queryContext['numRows']) && $queryContext['numRows'] === 250;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test aggregation hits handling
     */
    public function testAggregationHitsHandling(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'size' => 0, // Aggregation only
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

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->anything(),
                $this->callback(function ($logContext) {
                    if (!isset($logContext['query']) || !($logContext['query'] instanceof LoggedQuery)) {
                        return false;
                    }

                    $queryContext = $logContext['query']->getContext();

                    return isset($queryContext['numRows']) && $queryContext['numRows'] === 1000;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
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
