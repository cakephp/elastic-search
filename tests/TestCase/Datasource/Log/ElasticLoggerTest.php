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
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Tests the ElasticLogger class
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
     * Test log formatting for Elastica 9.x debug request format
     */
    public function testLogFormatting9xDebugRequest(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/test_index/_search',
                'data' => ['query' => ['match_all' => []]],
            ],
            'response' => [
                'took' => 15,
                'hits' => [
                    'total' => ['value' => 42],
                    'hits' => [],
                ],
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->isString(),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           $logContext['query'] instanceof LoggedQuery;
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test log formatting for new 9.x format with different context structure
     */
    public function testLogFormatting9xNewFormat(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'method' => 'GET',
            'path' => '/test_index/_doc/1',
            'data' => null,
            'request' => 'some_request_object', // Not array format
            'response' => [
                'found' => true,
                '_source' => ['title' => 'Test Document'],
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->isString(),
                $this->isArray(),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test log formatting with different response structures
     */
    public function testLogFormattingDifferentResponseStructures(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        // Test with count response
        $context = [
            'request' => [
                'method' => 'GET',
                'path' => '/test_index/_count',
                'data' => null,
            ],
            'response' => [
                'count' => 100,
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->isString(),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           ($logContext['query'] instanceof LoggedQuery);
                }),
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Elastica Request', $context);
    }

    /**
     * Test log formatting with legacy total format (direct number)
     */
    public function testLogFormattingLegacyTotalFormat(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $connection = $this->getMockConnection(true);

        $elasticLogger = new ElasticLogger($mockLogger, $connection);

        $context = [
            'request' => [
                'method' => 'POST',
                'path' => '/test_index/_search',
                'data' => ['query' => ['match_all' => []]],
            ],
            'response' => [
                'took' => 5,
                'hits' => [
                    'total' => 25, // Legacy format - direct number
                    'hits' => [],
                ],
            ],
        ];

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->isString(),
                $this->callback(function ($logContext) {
                    return isset($logContext['query']) &&
                           ($logContext['query'] instanceof LoggedQuery);
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

        $testException = new Exception('Test exception message');
        $context = [
            'exception' => $testException,
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test exception message');

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
            $elasticLogger->log($level, "Test message for $level", []);
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
                return 'Stringable message content';
            }
        };

        $mockLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, $this->stringContains('[]'), []);

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
            ->with(LogLevel::DEBUG, $this->stringContains('[]'), []);

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
                ],
            ],
            'unicode' => 'Special chars: áéíóú',
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
                           $decoded['nested']['deep']['structure'] === true;
                }),
                $complexContext,
            );

        $elasticLogger->log(LogLevel::DEBUG, 'Complex context test', $complexContext);
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
