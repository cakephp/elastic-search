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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Adapter to convert logs to QueryLogger readable content
 *
 * Handles the new elasticsearch-php package logging format with PSR-7 requests/responses.
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
     */
    public function getLogger(): QueryLogger|LoggerInterface
    {
        return $this->_logger;
    }

    /**
     * Format log messages from the Elastica client
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
     * Elastica log parameters with elasticsearch-php package:
     * -----------------------------------------------------------
     * error:
     *     message: "Elastica Request Failure"
     *     context: [ exception, request, retry ]
     * debug (PSR-7 request):
     *     message: "Elastica Request"
     *     context: [ request => PSR-7 RequestInterface, response => PSR-7 ResponseInterface, responseStatus => int ]
     * debug (array format):
     *     message: "Elastica Request"
     *     context: [ request => array, response => array, responseStatus => int ]
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array $context log context
     */
    protected function _log(string $level, string $message, array $context = []): void
    {
        $logData = $this->extractLogData($context);
        $logDataJson = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        if ($this->hasRequestResponse($context)) {
            $queryMetrics = $this->extractQueryMetrics($context);

            $loggedQuery = new LoggedQuery();
            $loggedQuery->setContext([
                'query' => $logDataJson,
                'took' => $queryMetrics['took'],
                'numRows' => $queryMetrics['numRows'],
            ]);

            $context['query'] = $loggedQuery;
        }

        $this->handleException($context);
        $this->getLogger()->log($level, $logDataJson, $context);
    }

    /**
     * Extract log data from context based on format type
     *
     * @param array $context Log context
     * @return array Extracted log data
     */
    protected function extractLogData(array $context): array
    {
        if (!isset($context['request'])) {
            // Return original context when no request data to extract
            return $context;
        }

        $request = $context['request'];

        // Handle PSR-7 RequestInterface (Nyholm\Psr7\ServerRequest)
        if ($request instanceof RequestInterface) {
            return $this->extractPsr7RequestData($request);
        }

        // Handle array format request
        if (is_array($request)) {
            return $this->extractArrayRequestData($request);
        }

        // Fallback to context as-is
        return $context;
    }

    /**
     * Extract request data from PSR-7 RequestInterface
     *
     * @param \Psr\Http\Message\RequestInterface $request PSR-7 request
     * @return array Extracted request data
     */
    protected function extractPsr7RequestData(RequestInterface $request): array
    {
        $body = (string)$request->getBody();
        $bodyData = null;

        if (!empty($body)) {
            $decodedBody = json_decode($body, true);
            $bodyData = $decodedBody ?? $body;
        }

        return [
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'headers' => $request->getHeaders(),
            'body' => $bodyData,
        ];
    }

    /**
     * Extract request data from array format
     *
     * @param array $request Array format request
     * @return array Extracted request data
     */
    protected function extractArrayRequestData(array $request): array
    {
        return [
            'method' => $request['method'] ?? null,
            'path' => $request['path'] ?? null,
            'data' => $request['data'] ?? null,
            'size' => $request['size'] ?? null,
            'from' => $request['from'] ?? null,
            'query' => $request['query'] ?? null,
        ];
    }

    /**
     * Check if context has both request and response
     *
     * @param array $context Log context
     */
    protected function hasRequestResponse(array $context): bool
    {
        return isset($context['request']) && isset($context['response']);
    }

    /**
     * Extract query metrics (timing and row count) from context
     *
     * @param array $context Log context
     * @return array Query metrics with 'took' and 'numRows' keys
     */
    protected function extractQueryMetrics(array $context): array
    {
        $took = 0;
        $numRows = 0;
        $response = $context['response'];

        // Handle PSR-7 ResponseInterface (Nyholm\Psr7\Response)
        if ($response instanceof ResponseInterface) {
            $responseBody = (string)$response->getBody();
            $responseData = json_decode($responseBody, true);

            if (is_array($responseData)) {
                $took = $responseData['took'] ?? 0;
                $numRows = $this->extractRowCount($responseData);
            }
        } elseif (is_array($response)) {
            // Handle array format response
            $took = $response['took'] ?? 0;
            $numRows = $this->extractRowCount($response);
        }

        return [
            'took' => $took,
            'numRows' => $numRows,
        ];
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

    /**
     * Handle exceptions in context
     *
     * @param array $context Log context
     * @throws \Exception If exception is present in context
     */
    protected function handleException(array $context): void
    {
        $exception = $context['exception'] ?? null;
        if ($exception instanceof Exception) {
            throw $exception;
        }
    }
}
