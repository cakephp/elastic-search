<?php

namespace Cake\ElasticSearch;

use Cake\Collection\CollectionTrait;
use Countable;
use IteratorIterator;
use JsonSerializable;

/**
 * Decorates the Elastica ResultSet in order to hydrate results with the
 * correct class and provide a Collection interface to the returned results.
 */
class ResultSet extends IteratorIterator implements Countable, JsonSerializable
{

    use CollectionTrait;

    /**
     * Holds the original instance of the result set
     *
     * @var string
     */
    protected $_resultSet;

    /**
     * The full class name of the document class to wrap the results
     *
     * @var \Cake\ElasticSearch\Document
     */
    protected $_entityClass;

    /**
     * Decorator's constructor
     *
     * @param \Elastica\ResultSet $resultSet The results from Elastica to wrap
     * @return void
     */
    public function __construct($resultSet, $query)
    {
        $this->_resultSet = $resultSet;
        $this->_entityClass = $query->repository()->entityClass();
        parent::__construct($resultSet);
    }

    /**
     * Returns all results
     *
     * @return Result[] Results
     */
    public function getResults()
    {
        return $this->_resultSet->getResults();
    }

    /**
     * Returns true if the response contains suggestion results; false otherwise
     *
     * @return bool
     */
    public function hasSuggests()
    {
        return $this->_resultSet->hasSuggests();
    }

    /**
    * Return all suggests
    *
    * @return array suggest results
    */
    public function getSuggests()
    {
        return $this->_resultSet->getSuggests();
    }

    /**
     * Returns whether facets exist
     *
     * @return boolean Facet existence
     */
    public function hasFacets()
    {
        return $this->_resultSet->hasFacets();
    }

    /**
     * Returns all facets results
     *
     * @return array Facet results
     */
    public function getFacets()
    {
        return $this->_resultSet->getFacets();
    }

    /**
     * Returns all aggregation results
     *
     * @return array
     */
    public function getAggregations()
    {
        return $this->_resultSet->getAggregations();
    }

    /**
     * Retrieve a specific aggregation from this result set
     *
     * @param string $name the name of the desired aggregation
     * @return array
     * @throws Exception\InvalidException if an aggregation by the given name cannot be found
     */
    public function getAggregation($name)
    {
        return $this->_resultSet->getAggregation($name);
    }

    /**
     * Returns the total number of found hits
     *
     * @return int Total hits
     */
    public function getTotalHits()
    {
        return $this->_resultSet->getTotalHits();
    }

    /**
     * Returns the max score of the results found
     *
     * @return float Max Score
     */
    public function getMaxScore()
    {
        return $this->_resultSet->getMaxScore();
    }

    /**
     * Returns the total number of ms for this search to complete
     *
     * @return int Total time
     */
    public function getTotalTime()
    {
        return $this->_resultSet->getTotalTime();
    }

    /**
     * Returns true if the query has timed out
     *
     * @return bool Timed out
     */
    public function hasTimedOut()
    {
        return $this->_resultSet->hasTimedOut();
    }

    /**
     * Returns response object
     *
     * @return \Elastica\Response Response object
     */
    public function getResponse()
    {
        return $this->_resultSet->getResponse();
    }

    /**
     * Returns the original \Elastica\Query instance
     *
     * @return \Elastica\Query
     */
    public function getQuery()
    {
        return $this->_resultSet->getQuery();
    }

    /**
     * Returns size of current set
     *
     * @return int Size of set
     */
    public function count()
    {
        return $this->_resultSet->count();
    }

    /**
     * Returns size of current suggests
     *
     * @return int Size of suggests
     */
    public function countSuggests()
    {
        return $this->_resultSet->countSuggests();
    }

    /**
     * Returns the current document for the iteration
     *
     * @return Cake\ElasticSearch\Document
     */
    public function current()
    {
        $class = $this->_entityClass;
        $options = [
            'markClean' => true,
            'useSetters' => false,
            'markNew' => false
        ];
        $document = new $class($this->_resultSet->current(), $options);
        return $document;
    }
}
