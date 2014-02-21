<?php

namespace Cake\ElasticSearch;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;
use Elastica\Result;

class Document implements EntityInterface {

	use EntityTrait;

	protected $_result;

	public function __construct($data = []) {
		if ($data instanceof Result) {
			$this->_result = $data;
			$data = $data->getData();
		}
		$this->set($data, ['guard' => false]);
	}

}
