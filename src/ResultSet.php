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
    protected $resultSet;

    /**
     * The full class name of the document class to wrap the results
     *
     * @var \Cake\ElasticSearch\Document
     */
    protected $entityClass;

    /**
     * Embedded type references
     *
     * @var array
     */
    protected $embeds = [];

    /**
     * Name of the type that the originating query came from.
     *
     * @var string
     */
    protected $repoName;

    /**
     * Decorator's constructor
     *
     * @param \Elastica\ResultSet $resultSet The results from Elastica to wrap
     * @param \Elastica\Query $query The Elasticsearch Query object
     */
    public function __construct($resultSet, $query)
    {
        $this->resultSet = $resultSet;
        $repo = $query->repository();
        foreach ($repo->embedded() as $embed) {
            $this->embeds[$embed->property()] = $embed;
        }
        $this->entityClass = $repo->entityClass();
        $this->repoName = $repo->name();
        parent::__construct($resultSet);
    }

    /**
     * Returns all results
     *
     * @return Result[] Results
     */
    public function getResults()
    {
        return $this->resultSet->getResults();
    }

    /**
     * Returns true if the response contains suggestion results; false otherwise
     *
     * @return bool
     */
    public function hasSuggests()
    {
        return $this->resultSet->hasSuggests();
    }

    /**
     * Return all suggests
     *
     * @return array suggest results
     */
    public function getSuggests()
    {
        return $this->resultSet->getSuggests();
    }

    /**
     * Returns whether facets exist
     *
     * @return bool Facet existence
     */
    public function hasFacets()
    {
        return $this->resultSet->hasFacets();
    }

    /**
     * Returns all facets results
     *
     * @return array Facet results
     */
    public function getFacets()
    {
        return $this->resultSet->getFacets();
    }

    /**
     * Returns all aggregation results
     *
     * @return array
     */
    public function getAggregations()
    {
        return $this->resultSet->getAggregations();
    }

    /**
     * Retrieve a specific aggregation from this result set
     *
     * @param string $name the name of the desired aggregation
     * @return array
     * @throws \Elastica\Exception\InvalidException if an aggregation by the given name cannot be found
     */
    public function getAggregation($name)
    {
        return $this->resultSet->getAggregation($name);
    }

    /**
     * Returns the total number of found hits
     *
     * @return int Total hits
     */
    public function getTotalHits()
    {
        return $this->resultSet->getTotalHits();
    }

    /**
     * Returns the max score of the results found
     *
     * @return float Max Score
     */
    public function getMaxScore()
    {
        return $this->resultSet->getMaxScore();
    }

    /**
     * Returns the total number of ms for this search to complete
     *
     * @return int Total time
     */
    public function getTotalTime()
    {
        return $this->resultSet->getTotalTime();
    }

    /**
     * Returns true if the query has timed out
     *
     * @return bool Timed out
     */
    public function hasTimedOut()
    {
        return $this->resultSet->hasTimedOut();
    }

    /**
     * Returns response object
     *
     * @return \Elastica\Response Response object
     */
    public function getResponse()
    {
        return $this->resultSet->getResponse();
    }

    /**
     * Returns the original \Elastica\Query instance
     *
     * @return \Elastica\Query
     */
    public function getQuery()
    {
        return $this->resultSet->getQuery();
    }

    /**
     * Returns size of current set
     *
     * @return int Size of set
     */
    public function count()
    {
        return $this->resultSet->count();
    }

    /**
     * Returns size of current suggests
     *
     * @return int Size of suggests
     */
    public function countSuggests()
    {
        return $this->resultSet->countSuggests();
    }

    /**
     * Returns the current document for the iteration
     *
     * @return \Cake\ElasticSearch\Document
     */
    public function current()
    {
        $class = $this->entityClass;
        $result = $this->resultSet->current();
        $options = [
            'markClean' => true,
            'useSetters' => false,
            'markNew' => false,
            'source' => $this->repoName,
            'result' => $result
        ];

        $data = $result->getData();
        $data['id'] = $result->getId();

        foreach ($this->embeds as $property => $embed) {
            if (isset($data[$property])) {
                $data[$property] = $embed->hydrate($data[$property], $options);
            }
        }
        $document = new $class($data, $options);

        return $document;
    }

    /**
     * Debug output hook method.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'items' => $this->resultSet->getResponse()->getData(),
            'query' => $this->resultSet->getQuery(),
        ];
    }
}
