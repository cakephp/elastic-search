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
use Exception;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Tests the ElasticLogger class for Elastica with PSR-7 support
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
     * Test log method when query logging is enabled
     */
    public function testLogWhenLoggingEnabled(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true); // Query logging enabled

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        // Expect the mock logger to be called
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::DEBUG, $this->stringContains('"test": "data"'), ['test' => 'data']);

        $elasticLogger->log(LogLevel::DEBUG, 'Test message', ['test' => 'data']);
    }

    /**
     * Test log method when query logging is disabled
     */
    public function testLogWhenLoggingDisabled(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(false); // Query logging disabled

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        // Expect the mock logger to NOT be called
        $mockLogger->expects($this->never())
            ->method('log');

        $elasticLogger->log(LogLevel::DEBUG, 'Test message', ['test' => 'data']);
    }

    /**
     * Test PSR-7 ServerRequest logging format (Nyholm\Psr7\ServerRequest)
     */
    public function testPsr7ServerRequestLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        // Create PSR-7 ServerRequest
        $uri = new Uri('http://localhost:9200/test_index/_search?routing=user1');
        $body = json_encode([
            'query' => [
                'match' => ['title' => 'test'],
            ],
            'size' => 10,
        ]);

        $request = new ServerRequest('POST', $uri, [
            'Content-Type' => 'application/json',
            'User-Agent' => 'elastica/9.0',
        ], $body);

        // Create PSR-7 Response
        $responseBody = json_encode([
            'took' => 25,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 142, 'relation' => 'eq'],
                'hits' => [
                    ['_id' => '1', '_source' => ['title' => 'Test Document']],
                ],
            ],
        ]);
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);

        $context = [
            'request' => $request,
            'response' => $response,
            'responseStatus' => 200,
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['method']) && $data['method'] === 'POST' &&
                           isset($data['path']) && $data['path'] === '/test_index/_search' &&
                           isset($data['body']['query']['match']['title']);
                }),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           $logContext['query'] instanceof LoggedQuery;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test PSR-7 Request logging format (Nyholm\Psr7\Request)
     */
    public function testPsr7RequestLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        // Create PSR-7 Request
        $uri = new Uri('http://localhost:9200/test_index/_doc/123');
        $body = json_encode(['title' => 'Updated Document']);

        $request = new Request('PUT', $uri, [
            'Content-Type' => 'application/json',
        ], $body);

        // Create PSR-7 Response
        $responseBody = json_encode([
            '_index' => 'test_index',
            '_id' => '123',
            '_version' => 2,
            'result' => 'updated',
        ]);
        $response = new Response(200, ['Content-Type' => 'application/json'], $responseBody);

        $context = [
            'request' => $request,
            'response' => $response,
            'responseStatus' => 200,
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['method']) && $data['method'] === 'PUT' &&
                           isset($data['path']) && $data['path'] === '/test_index/_doc/123' &&
                           isset($data['body']['title']);
                }),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           $logContext['query'] instanceof LoggedQuery;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test array format request logging (direct format from elasticsearch-php)
     */
    public function testArrayFormatRequestLogging(): void
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
                        'must' => [
                            ['match' => ['status' => 'active']],
                        ],
                    ],
                ],
            ],
            'response' => [
                'took' => 8,
                'timed_out' => false,
                '_shards' => [
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ],
                'hits' => [
                    'total' => ['value' => 25, 'relation' => 'eq'],
                    'hits' => [],
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
                           isset($data['query']['bool']['must']);
                }),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           $logContext['query'] instanceof LoggedQuery;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test legacy array format request logging
     */
    public function testLegacyArrayFormatRequestLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/test_index/_search',
                'data' => [
                    'query' => ['match_all' => []],
                    'size' => 20,
                ],
            ],
            'response' => [
                'took' => 15,
                'hits' => [
                    'total' => ['value' => 100, 'relation' => 'eq'],
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

                    return isset($data['method']) && $data['method'] === 'POST' &&
                           isset($data['path']) && $data['path'] === '/test_index/_search' &&
                           isset($data['data']['query']['match_all']);
                }),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           $logContext['query'] instanceof LoggedQuery;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test count operation response logging
     */
    public function testCountOperationLogging(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'GET',
                'path' => '/test_index/_count',
                'data' => [
                    'query' => ['term' => ['status' => 'published']],
                ],
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

                    return $queryContext['numRows'] === 87;
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

                    return $queryContext['numRows'] === 42;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test query metrics extraction (took and numRows)
     */
    public function testQueryMetricsExtraction(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/metrics_test/_search',
                'data' => ['query' => ['match_all' => []]],
            ],
            'response' => [
                'took' => 125,
                'hits' => [
                    'total' => ['value' => 1000, 'relation' => 'eq'],
                    'hits' => [
                        ['_id' => '1'],
                        ['_id' => '2'],
                        ['_id' => '3'],
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

                    return $queryContext['numRows'] === 1000 && $queryContext['took'] === 125.0;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test exception handling in log method
     */
    public function testExceptionHandling(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $testException = new Exception('ElasticSearch connection failed');
        $context = [
            'exception' => $testException,
            'request' => [
                'method' => 'POST',
                'path' => '/failing_index/_search',
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ElasticSearch connection failed');

        $elasticLogger->log(LogLevel::ERROR, 'Elastica Request Failure', $context);
    }

    /**
     * Test log method with different PSR-3 log levels
     */
    public function testDifferentLogLevels(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        $mockLogger->expects($this->exactly(count($levels)))
            ->method('log');

        foreach ($levels as $level) {
            $elasticLogger->log($level, 'Test message for ' . $level, []);
        }
    }

    /**
     * Test log with Stringable message
     */
    public function testStringableMessage(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $stringableMessage = new class implements Stringable {
            public function __toString(): string
            {
                return 'Stringable ElasticSearch message';
            }
        };

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, '[]', []);

        $elasticLogger->log(LogLevel::INFO, $stringableMessage, []);
    }

    /**
     * Test log with empty context
     */
    public function testLogWithEmptyContext(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::DEBUG, '[]', []);

        $elasticLogger->log(LogLevel::DEBUG, 'Simple message');
    }

    /**
     * Test log with complex nested context data
     */
    public function testLogWithComplexContext(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $complexContext = [
            'nested' => [
                'deep' => [
                    'structure' => true,
                    'values' => [1, 2, 3],
                    'elasticsearch_query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['status' => 'active']],
                            ],
                        ],
                    ],
                ],
            ],
            'unicode' => 'Special chars: áéíóú 中文 🔍',
            'null_value' => null,
            'boolean' => false,
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $decoded = json_decode($message, true);

                    return is_array($decoded) &&
                           isset($decoded['nested']['deep']['structure']) &&
                           $decoded['nested']['deep']['structure'] === true &&
                           isset($decoded['unicode']) &&
                           str_contains($decoded['unicode'], '🔍');
                }),
                $complexContext,
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Complex context test', $complexContext);
    }

    /**
     * Test PSR-7 request with empty body
     */
    public function testPsr7RequestWithEmptyBody(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $uri = new Uri('http://localhost:9200/test_index/_refresh');
        $request = new Request('POST', $uri);

        $context = [
            'request' => $request,
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['method']) && $data['method'] === 'POST' &&
                           isset($data['path']) && $data['path'] === '/test_index/_refresh' &&
                           $data['body'] === null;
                }),
                $context,
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test request with non-JSON body
     */
    public function testRequestWithNonJsonBody(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);
        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $uri = new Uri('http://localhost:9200/test_index/_analyze');
        $plainTextBody = 'analyze this text';
        $request = new Request('POST', $uri, ['Content-Type' => 'text/plain'], $plainTextBody);

        $context = [
            'request' => $request,
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) use ($plainTextBody) {
                    $data = json_decode($message, true);

                    return isset($data['body']) && $data['body'] === $plainTextBody;
                }),
                $context,
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
                'method' => 'DELETE',
                'path' => '/test_index',
                'data' => null,
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->callback(function ($message) {
                    $data = json_decode($message, true);

                    return isset($data['method']) && $data['method'] === 'DELETE';
                }),
                $this->callback(function ($logContext) {
                    // Should not create LoggedQuery when no response
                    return !isset($logContext['query']);
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Helper method to create a mock Connection
     */
    private function getMockConnection(bool $queryLoggingEnabled = true): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isQueryLoggingEnabled')
            ->willReturn($queryLoggingEnabled);

        return $connection;
    }
}
