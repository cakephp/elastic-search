<?php

namespace Cake\ElasticSearch\Datasource;

use Cake\Log\Log;
use Elastica\Client;
use Elastica\Request;

class Connection extends Client {

	public function config() {
		return $this->_config;
	}

	protected function _log($context) {
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
			Log::write('debug', $data, ['elasticSearhLog']);
		}
	}

}
