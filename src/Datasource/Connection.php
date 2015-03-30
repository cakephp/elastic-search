<?php

namespace Cake\ElasticSearch\Datasource;

use Cake\Log\Log;
use Elastica\Client;
use Elastica\Request;

class Connection extends Client
{

    /**
     * Constructor. Appends the default index name to the config array, which by default
     * is `_all`
     *
     * @param array $config config options
     * @param callback $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        $config += ['index' => '_all'];
        parent::__construct($config, $callback);
    }

    public function config()
    {
        return $this->_config;
    }

    /**
     * Returns the index for the given connection
     *
     * @param  string $name Index name to create connection to, if no value is passed
     * it will use the default index name for the connection.
     * @return \Elastica\Index Index for the given name
     */
    public function getIndex($name = null)
    {
        return parent::getIndex($name ?: $this->getConfig('index'));
    }

    protected function _log($context) 
    {
        if ($this->_logger) {
            parent::_log($context);
        }

        if ($this->getConfig('log')) {
            if ($context instanceof Request) {
                $data = $context->toArray();
            } else {
                $data = ['message' => $context];
            }

            $data = json_encode($data, JSON_PRETTY_PRINT);
            Log::write('debug', $data, ['elasticSearchLog']);
        }
    }
}
