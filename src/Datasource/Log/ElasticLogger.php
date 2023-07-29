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
 * Adapter to convert elastic logs to QueryLogger readable content
 */
class ElasticLogger extends AbstractLogger
{
    /**
     * Holds the logger instance
     *
     * @var \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface
     */
    protected QueryLogger|LoggerInterface $_logger;

    /**
     * Holds the connection instance
     *
     * @var \Cake\ElasticSearch\Datasource\Connection
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
     * @return void
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        if ($this->_connection->isQueryLoggingEnabled()) {
            $this->_log($level, $message, $context);
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
     * @return void
     */
    protected function _log(string $level, string $message, array $context = []): void
    {
        $logData = $context;
        if ($level === LogLevel::DEBUG && isset($context['request'])) {
            $logData = [
                'method' => $context['request']['method'],
                'path' => $context['request']['path'],
                'data' => $context['request']['data'],
            ];
        }
        $logData = json_encode($logData, JSON_PRETTY_PRINT);

        if (isset($context['request'], $context['response'])) {
            $took = 0;
            $numRows = $context['response']['hits']['total']['value'] ?? $context['response']['hits']['total'] ?? 0;
            if (isset($context['response']['took'])) {
                $took = $context['response']['took'];
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
        if ($exception instanceof \Exception) {
            throw $exception;
        }
        $this->getLogger()->log($level, $logData, $context);
    }
}
