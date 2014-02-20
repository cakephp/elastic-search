<?php

namespace Cake\ElasticSearch;

use Cake\Collection\CollectionTrait;
use Countable;
use IteratorIterator;
use JsonSerializable;

/**
 * Decorates the Elastica ResultSet in order to hydrate results with the
 * correct class.
 */
class ResultSet extends IteratorIterator implements Countable, JsonSerializable {

	use CollectionTrait;

/**
 * Holds the original instance of the result set
 *
 * @var string
 */
	protected $_resultSet;

/**
 * Decorator's constructor
 *
 * @param \Elastica\ResultSet $resultSet The results from Elastica to wrap
 * @return void
 */
	public function __construct($resultSet) {
		$this->_resultSet = $resultSet;
		parent::__construct($resultSet);
	}

/**
 * Returns all results
 *
 * @return Result[] Results
 */
	public function getResults(){
		return $this->_resultSet->getResults();
	}

/**
 * Returns whether facets exist
 *
 * @return boolean Facet existence
 */
	public function hasFacets() {
		return $this->_resultSet->hasFacets();
	}

/**
 * Returns all facets results
 *
 * @return array Facet results
 */
	public function getFacets() {
		return $this->_resultSet->getFacets();
	}

/**
 * Returns the total number of found hits
 *
 * @return int Total hits
 */
	public function getTotalHits() {
		return $this->_resultSet->getTotalHits();
	}

/**
 * Returns the max score of the results found
 *
 * @return float Max Score
 */
	public function getMaxScore() {
		return $this->_resultSet->getMaxScore();
	}

/**
 * Returns the total number of ms for this search to complete
 *
 * @return int Total time
 */
	public function getTotalTime() {
		return $this->_resultSet->getTotalTime();
	}

/**
 * Returns true if the query has timed out
 *
 * @return bool Timed out
 */
	public function hasTimedOut() {
		return $this->_resultSet->hasTimedOut();
	}

/**
 * Returns response object
 *
 * @return \Elastica\Response Response object
 */
	public function getResponse() {
		return $this->_resultSet->getResponse();
	}

/**
 * @return \Elastica\Query
 */
	public function getQuery() {
		return $this->_resultSet->getQuery();
	}

/**
 * Returns size of current set
 *
 * @return int Size of set
 */
	public function count() {
		return $this->_resultSet->count();
	}

}
