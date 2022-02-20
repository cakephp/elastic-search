<?php
declare(strict_types=1);

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
use Cake\Datasource\ResultSetInterface;
use Elastica\Query as ElasticaQuery;
use Elastica\Response;
use Elastica\ResultSet as ElasticaResultSet;
use IteratorIterator;
use ReturnTypeWillChange;

/**
 * Decorates the Elastica ResultSet in order to hydrate results with the
 * correct class and provide a Collection interface to the returned results.
 */
class ResultSet extends IteratorIterator implements ResultSetInterface
{
    use CollectionTrait;

    /**
     * Holds the original instance of the result set
     *
     * @var \Elastica\ResultSet
     */
    protected ElasticaResultSet $resultSet;

    /**
     * Holds the Elasticsearch ORM query object
     *
     * @var \Cake\ElasticSearch\Query
     */
    protected Query $queryObject;

    /**
     * The full class name of the document class to wrap the results
     *
     * @var string
     */
    protected string $entityClass;

    /**
     * Embedded type references
     *
     * @var array
     */
    protected array $embeds = [];

    /**
     * Name of the type that the originating query came from.
     *
     * @var string
     */
    protected string $repoName;

    /**
     * Decorator's constructor
     *
     * @param \Elastica\ResultSet $resultSet The results from Elastica to wrap
     * @param \Cake\ElasticSearch\Query $query The Elasticsearch Query object
     */
    public function __construct(ElasticaResultSet $resultSet, Query $query)
    {
        $this->resultSet = $resultSet;
        $this->queryObject = $query;
        $repo = $this->queryObject->getRepository();
        foreach ($repo->embedded() as $embed) {
            $this->embeds[$embed->getProperty()] = $embed;
        }
        $this->entityClass = $repo->getEntityClass();
        $this->repoName = $repo->getRegistryAlias();
        parent::__construct($resultSet);
    }

    /**
     * Returns all results
     *
     * @return array<\Elastica\Result> Results
     */
    public function getResults(): array
    {
        return $this->resultSet->getResults();
    }

    /**
     * Returns true if the response contains suggestion results; false otherwise
     *
     * @return bool
     */
    public function hasSuggests(): bool
    {
        return $this->resultSet->hasSuggests();
    }

    /**
     * Return all suggests
     *
     * @return array suggest results
     */
    public function getSuggests(): array
    {
        return $this->resultSet->getSuggests();
    }

    /**
     * Returns all aggregation results
     *
     * @return array
     */
    public function getAggregations(): array
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
    public function getAggregation(string $name): array
    {
        return $this->resultSet->getAggregation($name);
    }

    /**
     * Returns the total number of found hits
     *
     * @return int Total hits
     */
    public function getTotalHits(): int
    {
        return $this->resultSet->getTotalHits();
    }

    /**
     * Returns the max score of the results found
     *
     * @return float Max Score
     */
    public function getMaxScore(): float
    {
        return $this->resultSet->getMaxScore();
    }

    /**
     * Returns the total number of ms for this search to complete
     *
     * @return int Total time
     */
    public function getTotalTime(): int
    {
        return $this->resultSet->getTotalTime();
    }

    /**
     * Returns true if the query has timed out
     *
     * @return bool Timed out
     */
    public function hasTimedOut(): bool
    {
        return $this->resultSet->hasTimedOut();
    }

    /**
     * Returns response object
     *
     * @return \Elastica\Response Response object
     */
    public function getResponse(): Response
    {
        return $this->resultSet->getResponse();
    }

    /**
     * Returns the original \Elastica\Query instance
     *
     * @return \Elastica\Query
     */
    public function getQuery(): ElasticaQuery
    {
        return $this->resultSet->getQuery();
    }

    /**
     * Returns size of current set
     *
     * @return int Size of set
     */
    public function count(): int
    {
        return (int)$this->resultSet->count();
    }

    /**
     * Returns size of current suggests
     *
     * @return int Size of suggests
     */
    public function countSuggests(): int
    {
        return $this->resultSet->countSuggests();
    }

    /**
     * Returns the current document for the iteration
     *
     * @return \Cake\ElasticSearch\Document
     */
    #[ReturnTypeWillChange]
    public function current(): Document
    {
        $class = $this->entityClass;
        $result = $this->resultSet->current();
        $options = [
            'markClean' => true,
            'useSetters' => false,
            'markNew' => false,
            'source' => $this->repoName,
            'result' => $result,
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
     * Returns a string representation of this object that can be used
     * to reconstruct it
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize([ $this->resultSet, $this->queryObject ]);
    }

    /**
     * Magic method for serializing the ResultSet instance
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [$this->resultSet, $this->queryObject];
    }

    /**
     * Unserializes the passed string and rebuilds the ResultSet instance
     *
     * @param string $serialized The serialized ResultSet information
     * @return void
     */
    public function unserialize(string $serialized): void
    {
        $this->__construct(...unserialize($serialized));
    }

    /**
     * Magic method for unserializing the ResultSet instance
     *
     * @param array $data The serialized data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->__construct(...$data);
    }

    /**
     * Debug output hook method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'items' => $this->resultSet->getResponse()->getData(),
            'query' => $this->resultSet->getQuery(),
        ];
    }
}
