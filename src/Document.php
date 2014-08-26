<?php

namespace Cake\ElasticSearch;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;
use Elastica\Result;

/**
 * Represents a document stored in a Elastic Search Type
 *
 */
class Document implements EntityInterface {

	use EntityTrait;

/**
 * Holds an instance to a Result object is passed in the constructor from
 * a search query. It can contain extra information about this document
 * concerning the search operation, such as highlights, score and version.
 *
 * @var \Elastica\Result
 */
	protected $_result;

/**
 * Takes either an array or a Result object form a serach and constructs
 * a document representing an enty in a elastic search type,
 *
 * @param array|Elastica\Result $data
 */
	public function __construct($data = []) {
		if ($data instanceof Result) {
			$this->_result = $data;
			$data = $data->getData();
		}

		$this->set($data, ['guard' => false]);
	}

}
