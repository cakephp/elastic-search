<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Datasource\Log;

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Adapter to convert elastic logs to QueryLogger readable content
 */
class ElasticLogger extends AbstractLogger
{
    /**
     * Holds the logger instance
     *
     * @var \Cake\Database\Log\QueryLogger|\Cake\Log\Engine\BaseLog
     */
    protected $_logger;

    /**
     * Holds the connection instance
     * @var \Cake\ElasticSearch\Datasource\Connection
     */
    protected $_connection;

    /**
     * Constructor, set the QueryLogger instance
     * @param \Cake\Database\Log\QueryLogger|\Cake\Log\Engine\BaseLog $logger Instance of the QueryLogger
     * @param \Cake\Datasource\ConnectionInterface $connection Current connection instance
     */
    public function __construct($logger, ConnectionInterface $connection)
    {
        $this->setLogger($logger);
        $this->_connection = $connection;
    }

    /**
     * Set the current cake logger
     *
     * @param \Cake\Database\Log\QueryLogger|\Cake\Log\Engine\BaseLog $logger Set logger instance to pass logging data to
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;

        return $this;
    }

    /**
     * Return the current logger
     * @return \Cake\Database\Log\QueryLogger|\Cake\Log\Engine\BaseLog|\Psr\Log\NullLogger [description]
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Format log messages from the Elastica client _log method
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array $context log context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->_connection->logQueries()) {
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
     *     context: [ request, response, responseStatus ]
     * debug (fallback?):
     *     message: "Elastica Request"
     *     context: [ message ]
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array $context log context
     * @return void
     */
    protected function _log($level, $message, array $context = [])
    {
        $logData = $context;

        if (LogLevel::DEBUG && isset($context['request'])) {
            $logData = [
                'method' => $context['request']['method'],
                'path' => $context['request']['path'],
                'data' => $context['request']['data']
            ];
        }

        $logData = json_encode($logData, JSON_PRETTY_PRINT);

        if ($this->getLogger() instanceof QueryLogger) {
            $took = $numRows = 0;
            if (isset($context['response']['took'])) {
                $took = $context['response']['took'];
            }
            if (isset($context['response']['hits']['total'])) {
                $numRows = $context['response']['hits']['total'];
            }

            $message = new LoggedQuery();
            $message->query = $logData;
            $message->took = $took;
            $message->numRows = $numRows;

            $this->getLogger()->log($message);
        } else {
            $this->getLogger()->log($level, $logData, $context);
        }
    }
}
