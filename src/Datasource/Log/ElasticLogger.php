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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Adapter to convert Elastica logs to QueryLogger readable content
 *
 * Handles DEBUG level logging with simple request/response array format.
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
     * @return \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface
     */
    public function getLogger(): QueryLogger|LoggerInterface
    {
        return $this->_logger;
    }

    /**
     * Format log messages from the Elastica client
     *
     * Only processes DEBUG level logs with request/response arrays.
     *
     * @param mixed $level The log level
     * @param \Stringable|string $message The log message
     * @param array $context log context
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        if ($this->_connection->isQueryLoggingEnabled() && $level === LogLevel::DEBUG) {
            $this->_log($level, (string)$message, $context);
        }
    }

    /**
     * Format DEBUG log messages from the Elastica client and pass
     * them to the cake defined logger instance
     *
     * Elastica DEBUG log parameters:
     * ------------------------------
     * message: "Elastica Request"
     * context: [ request => array, response => array, responseStatus => int ]
     *
     * Where:
     * - request: [ size, from, query, method, path, data, etc. ]
     * - response: [ took, hits => [ total => int|array ], etc. ]
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array $context log context
     */
    protected function _log(string $level, string $message, array $context = []): void
    {
        // Only process if we have request data
        if (!isset($context['request']) || !is_array($context['request'])) {
            return;
        }

        // Extract and format request data
        $request = $context['request'];
        $logData = [
            'method' => $request['method'] ?? null,
            'path' => $request['path'] ?? null,
            'data' => $request['data'] ?? null,
            'size' => $request['size'] ?? null,
            'from' => $request['from'] ?? null,
            'query' => $request['query'] ?? null,
        ];

        $logDataJson = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        // If we have response data, create LoggedQuery with metrics
        if (isset($context['response']) && is_array($context['response'])) {
            $response = $context['response'];

            // Extract timing information
            $took = (float)($response['took'] ?? 0);

            // Extract row count from various response formats
            $numRows = $this->extractRowCount($response);

            $loggedQuery = new LoggedQuery();
            $loggedQuery->setContext([
                'query' => $logDataJson,
                'took' => $took,
                'numRows' => $numRows,
            ]);

            $context['query'] = $loggedQuery;
        }

        $this->getLogger()->log($level, $logDataJson, $context);
    }

    /**
     * Extract row count from response data
     *
     * @param array $response Response data
     * @return int Number of rows/documents
     */
    protected function extractRowCount(array $response): int
    {
        // Handle search response with hits.total.value (ES 7.0+)
        if (isset($response['hits']['total']['value'])) {
            return (int)$response['hits']['total']['value'];
        }

        // Handle search response with hits.total as number (ES 6.x and earlier)
        if (isset($response['hits']['total']) && is_numeric($response['hits']['total'])) {
            return (int)$response['hits']['total'];
        }

        // Handle count response
        if (isset($response['count'])) {
            return (int)$response['count'];
        }

        // Handle aggregation responses or other operations
        if (isset($response['hits']['hits']) && is_array($response['hits']['hits'])) {
            return count($response['hits']['hits']);
        }

        return 0;
    }
}
