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
namespace Cake\ElasticSearch\Datasource\Log;

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;
use Cake\ElasticSearch\Datasource\Connection;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Adapter to convert elastic logs to QueryLogger readable content
 */
class ElasticLogger extends AbstractLogger
{
    /**
     * Holds the logger instance
     */
    protected QueryLogger|LoggerInterface $_logger;

    /**
     * Holds the connection instance
     */
    protected Connection $_connection;

    /**
     * Constructor, set the QueryLogger instance
     *
     * @param \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface $logger Instance of the QueryLogger
     * @param \Cake\ElasticSearch\Datasource\Connection $connection Current connection instance
     */
    public function __construct(QueryLogger|LoggerInterface $logger, Connection $connection)
    {
        $this->setLogger($logger);
        $this->_connection = $connection;
    }

    /**
     * Set the current cake logger
     *
     * @param \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface $logger Set logger instance to pass logging data to
     * @return $this
     */
    public function setLogger(QueryLogger|LoggerInterface $logger)
    {
        $this->_logger = $logger;

        return $this;
    }

    /**
     * Return the current logger
     *
     * @return \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface [description]
     */
    public function getLogger(): QueryLogger|LoggerInterface
    {
        return $this->_logger;
    }

    /**
     * Format log messages from the Elastica client _log method
     *
     * @param mixed $level The log level
     * @param \Stringable|string $message The log message
     * @param array $context log context
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        if ($this->_connection->isQueryLoggingEnabled()) {
            $this->_log($level, (string)$message, $context);
        }
    }

    /**
     * Format log messages from the Elastica client and pass
     * them to the cake defined logger instance
     *
     * Elastica's log parameters
     * -------------------------
     * error:
     *     message: "Elastica Request Failure"
     *     context: [ exception, request, retry ]
     * debug (request):
     *     message: "Elastica Request"
     *     context: [ request, response, responseStatus, query ]
     * debug (fallback?):
     *     message: "Elastica Request"
     *     context: [ message, query ]
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array $context log context
     */
    protected function _log(string $level, string $message, array $context = []): void
    {
        $logData = $context;

        // Handle Elastica 9.x log format
        if ($level === LogLevel::DEBUG && isset($context['request'])) {
            // Handle legacy 7.x format
            if (is_array($context['request']) && isset($context['request']['method'])) {
                $logData = [
                    'method' => $context['request']['method'],
                    'path' => $context['request']['path'],
                    'data' => $context['request']['data'],
                ];
            } else {
                // Handle new 9.x format - context may have different structure
                $logData = [
                    'method' => $context['method'] ?? null,
                    'path' => $context['path'] ?? null,
                    'data' => $context['data'] ?? null,
                ];
            }
        }
        $logData = json_encode($logData, JSON_PRETTY_PRINT);

        if (isset($context['request'], $context['response'])) {
            $took = 0;
            $numRows = 0;

            // Handle response structure differences between Elastica versions
            if (is_array($context['response'])) {
                $response = $context['response'];
                $took = $response['took'] ?? 0;

                // Handle different response structures for document count
                if (isset($response['hits']['total']['value'])) {
                    $numRows = $response['hits']['total']['value'];
                } elseif (isset($response['hits']['total'])) {
                    $numRows = is_array($response['hits']['total']) ?
                        ($response['hits']['total']['value'] ?? 0) :
                        $response['hits']['total'];
                } elseif (isset($response['count'])) {
                    $numRows = $response['count'];
                }
            }

            $message = new LoggedQuery();
            $message->setContext([
                'query' => $logData,
                'took' => $took,
                'numRows' => $numRows,
            ]);

            $context['query'] = $message;
        }

        $exception = $context['exception'] ?? null;
        if ($exception instanceof Exception) {
            throw $exception;
        }

        $this->getLogger()->log($level, $logData ?: '', $context);
    }
}
