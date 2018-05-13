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
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Adapter to convert elastic logs to QueryLogger readable content
 */
class QueryLoggerAdapter extends AbstractLogger
{
    /**
     * Holds the QueryLogger instance
     *
     * @var \Cake\Database\Log\LoggedQuery
     */
    protected $_queryLogger;

    /**
     * Constructor, set the QueryLogger instance
     * @param \Cake\Database\Log\LoggedQuery $logger Instance of the QueryLogger
     */
    public function __construct(QueryLogger $logger)
    {
        $this->_queryLogger = $logger;
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
        $logData = $context;

        if (LogLevel::DEBUG && isset($context['request'])) {
            $logData = [
                'method' => $context['request']['method'],
                'path' => $context['request']['path'],
                'data' => $context['request']['data']
            ];
        }

        $loggedQuery = new LoggedQuery();
        $loggedQuery->query = json_encode($logData, JSON_PRETTY_PRINT);

        $this->_queryLogger->log($loggedQuery);
    }
}
