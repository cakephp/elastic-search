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

use Cake\Datasource\QueryInterface;
use Cake\Datasource\QueryTrait;
use Cake\Datasource\ResultSetInterface;
use Elastica\Query as ElasticaQuery;
use Elastica\Query\AbstractQuery;
use IteratorAggregate;

class Query implements IteratorAggregate, QueryInterface
{
    use QueryTrait;

    /**
     * Indicates that the operation should append to the list
     *
     * @var int
     */
    public const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var int
     */
    public const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var bool
     */
    public const OVERWRITE = true;

    /**
     * The Elastica Query object that is to be executed after
     * being built.
     *
     * @var \Elastica\Query
     */
    protected $_elasticQuery;

    /**
     * The various query builder parts that will
     * be transferred to the elastica query.
     *
     * @var array
     */
    protected $_queryParts = [
        'fields' => [],
        'limit' => null,
        'offset' => null,
        'order' => [],
        'highlight' => null,
        'aggregations' => [],
        'query' => null,
        'filter' => null,
        'postFilter' => null,
    ];

    /**
     * Internal state to track whether or not the query has been modified.
     *
     * @var bool
     */
    protected $_dirty = false;

    /**
     * Additional options for Elastica\Index::search()
     *
     * @see \Elastica\Search::OPTION_SEARCH_* constants
     * @var array
     */
    protected $_searchOptions = [];

    /**
     * Query constructor
     *
     * @param \Cake\ElasticSearch\Index $repository The type of document.
     */
    public function __construct(Index $repository)
    {
        $this->repository($repository);
        $this->_elasticQuery = new ElasticaQuery();
    }

    /**
     * Adds fields to be selected from _source.
     *
     * Calling this function multiple times will append more fields to the
     * list of fields to be selected from _source.
     *
     * If `true` is passed in the second argument, any previous selections
     * will be overwritten with the list passed in the first argument.
     *
     * @param array $fields The list of fields to select from _source.
     * @param bool $overwrite Whether or not to replace previous selections.
     * @return $this
     */
    public function select($fields, bool $overwrite = false)
    {
        if (!$overwrite) {
            $fields = array_merge($this->_queryParts['fields'], $fields);
        }
        $this->_queryParts['fields'] = $fields;

        return $this;
    }

    /**
     * Sets the maximum number of results to return for this query.
     * This sets the `size` option for the Elastic Search query.
     *
     * @param int $limit The number of documents to return.
     * @return $this
     */
    public function limit($limit)
    {
        $this->_queryParts['limit'] = (int)$limit;

        return $this;
    }

    /**
     * Sets the number of records that should be skipped from the original result set
     * This is commonly used for paginating large results. Accepts an integer.
     *
     * @param int $num The number of records to be skipped
     * @return $this
     */
    public function offset($num)
    {
        $this->_queryParts['offset'] = (int)$num;

        return $this;
    }

    /**
     * Set the page of results you want.
     *
     * This method provides an easier to use interface to set the limit + offset
     * in the record set you want as results. If empty the limit will default to
     * the existing limit clause, and if that too is empty, then `25` will be used.
     *
     * Pages should start at 1.
     *
     * @param int $num The page number you want.
     * @param int $limit The number of rows you want in the page. If null
     *  the current limit clause will be used.
     * @return $this
     */
    public function page(int $num, ?int $limit = null)
    {
        if ($limit !== null) {
            $this->limit($limit);
        }
        $limit = $this->clause('limit');
        if ($limit === null) {
            $limit = 25;
            $this->limit($limit);
        }
        $offset = ($num - 1) * $limit;
        if (PHP_INT_MAX <= $offset) {
            $offset = PHP_INT_MAX;
        }
        $this->offset((int)$offset);

        return $this;
    }

    /**
     * Returns any data that was stored in the specified clause. This is useful for
     * modifying any internal part of the query and it is used during compiling
     * to transform the query accordingly before it is executed. The valid clauses that
     * can be retrieved are: fields, filter, postFilter, query, order, limit and offset.
     *
     * The return value for each of those parts may vary. Some clauses use QueryExpression
     * to internally store their state, some use arrays and others may use booleans or
     * integers. This is summary of the return types for each clause.
     *
     * - fields: array, will return empty array when no fields are set
     * - query: The final BoolQuery to be used in the query (with scoring) part.
     * - filter: The query to use in the final BoolQuery filter object, returns null when not set
     * - postFilter: The query to use in the post_filter object, returns null when not set
     * - order: OrderByExpression, returns null when not set
     * - limit: integer, null when not set
     * - offset: integer, null when not set
     *
     * @param string $name name of the clause to be returned
     * @return mixed
     */
    public function clause($name)
    {
        return $this->_queryParts[$name];
    }

    /**
     * Sets the sorting options for the result set.
     *
     * The accepted format for the $order parameter is:
     *
     * - [['name' => ['order'=> 'asc', ...]], ['price' => ['order'=> 'asc', ...]]]
     * - ['name' => 'asc', 'price' => 'desc']
     * - 'field1' (defaults to order => 'desc')
     *
     * @param string|array $order The sorting order to use.
     * @param bool $overwrite Whether or not to replace previous sorting.
     * @return $this
     */
    public function order($order, $overwrite = false)
    {
        // [['field' => [...]], ['field2' => [...]]]
        if (is_array($order) && is_numeric(key($order))) {
            if ($overwrite) {
                $this->_queryParts['order'] = $order;

                return $this;
            }
            $this->_queryParts['order'] = array_merge($order, $this->_queryParts['order']);

            return $this;
        }

        if (is_string($order)) {
            $order = [$order => ['order' => 'desc']];
        }

        $normalizer = function ($order, $key) {
            // ['field' => 'asc|desc']
            if (is_string($order)) {
                return [$key => ['order' => $order]];
            }

            return [$key => $order];
        };

        $order = collection($order)->map($normalizer)->toList();

        if (!$overwrite) {
            $order = array_merge($this->_queryParts['order'], $order);
        }

        $this->_queryParts['order'] = $order;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $finder The finder method to use.
     * @param array $options The options for the finder.
     * @return \Cake\ElasticSearch\Query
     */
    public function find($finder = 'all', array $options = [])
    {
        return $this->_repository->callFinder($finder, $this, $options);
    }

    /**
     * Sets the filter to use in the query object. Queries added using this method
     * will be stacked on a bool query and applied to the filter part of the final BoolQuery.
     *
     * Filters added with this method will have no effect in the final score of the documents,
     * and the documents that do not match the specified filters will be left out.
     *
     * There are several way in which you can use this method. The easiest one is by passing
     * a simple array of conditions:
     *
     * {{{
     *   // Generates a {"term": {"name": "jose"}} json query
     *   $query->where(['name' => 'jose']);
     * }}}
     *
     * You can have as many conditions in the array as you'd like, Operators are also allowe in
     * the field side of the array:
     *
     * {{{
     *   $query->where(['name' => 'jose', 'age >' => 30, 'interests in' => ['php', 'cake']);
     * }}}
     *
     * You can read about the available operators and how they translate to Elastic Search
     * queries in the `Cake\ElasticSearch\QueryBuilder::parse()` method documentation.
     *
     * Additionally, it is possible to use a closure as first argument. The closure will receive
     * a QueryBuilder instance, that you can use for creating arbitrary queries combinations:
     *
     * {{{
     *   $query->where(function ($builder) {
     *    return $builder->and($builder->between('age', 10, 20), $builder->missing('name'));
     *   });
     * }}}
     *
     * Finally, you can pass any already built queries as first argument:
     *
     * {{{
     *   $query->where(new \Elastica\Filter\Term('name.first', 'jose'));
     * }}{
     *
     * @param array|null|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     * @see \Cake\ElasticSearch\QueryBuilder
     */
    public function where($conditions = null, array $types = [], bool $overwrite = false)
    {
        if (is_bool($types)) {
            $overwrite = $types;
        }

        return $this->_buildBoolQuery('filter', $conditions, $overwrite);
    }

    /**
     * Connects any previously defined set of conditions to the provided list
     * using the AND operator. This function accepts the conditions list in the same
     * format as the method `where` does, hence you can use arrays, expression objects
     * callback functions or strings.
     *
     * It is important to notice that when calling this function, any previous set
     * of conditions defined for this query will be treated as a single argument for
     * the AND operator. This function will not only operate the most recently defined
     * condition, but all the conditions as a whole.
     *
     * When using an array for defining conditions, creating constraints form each
     * array entry will use the same logic as with the `where()` function. This means
     * that each array entry will be joined to the other using the AND operator, unless
     * you nest the conditions in the array using other operator.
     *
     * ### Examples:
     *
     * ```
     * $query->where(['title' => 'Hello World')->andWhere(['author_id' => 1]);
     * ```
     *
     * Will produce:
     *
     * `WHERE title = 'Hello World' AND author_id = 1`
     *
     * ```
     * $query
     *   ->where(['OR' => ['published' => false, 'published is NULL']])
     *   ->andWhere(['author_id' => 1, 'comments_count >' => 10])
     * ```
     *
     * Produces:
     *
     * `WHERE (published = 0 OR published IS NULL) AND author_id = 1 AND comments_count > 10`
     *
     * ```
     * $query
     *   ->where(['title' => 'Foo'])
     *   ->andWhere(function ($exp, $query) {
     *     return $exp
     *       ->or(['author_id' => 1])
     *       ->add(['author_id' => 2]);
     *   });
     * ```
     *
     * Generates the following conditions:
     *
     * `WHERE (title = 'Foo') AND (author_id = 1 OR author_id = 2)`
     *
     * @param array|null|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @see \Cake\ElasticSearch\Query::where()
     * @see \Cake\ElasticSearch\QueryBuilder
     * @return $this
     */
    public function andWhere($conditions, array $types = [])
    {
        return $this->_buildBoolQuery('filter', $conditions, false, 'addMust');
    }

    /**
     * Modifies the query part, taking scores in account. Queries added using this method
     * will be stacked on a bool query and applied to the `must` part of the final BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param array|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return \Cake\ElasticSearch\Query
     */
    public function queryMust($conditions, $overwrite = false)
    {
        return $this->_buildBoolQuery('query', $conditions, $overwrite);
    }

    /**
     * Modifies the query part, taking scores in account. Queries added using this method
     * will be stacked on a bool query and applied to the `should` part of the final BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param array|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return \Cake\ElasticSearch\Query
     */
    public function queryShould($conditions, $overwrite = false)
    {
        return $this->_buildBoolQuery('query', $conditions, $overwrite, 'addShould');
    }

    /**
     * Sets the query to use in the post_filter object. Filters added using this method
     * will be stacked on a BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param array|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous filters.
     * @return $this
     * @see \Cake\ElasticSearch\Query::where()
     */
    public function postFilter($conditions, $overwrite = false)
    {
        return $this->_buildBoolQuery('postFilter', $conditions, $overwrite);
    }

    /**
     * Method to set or overwrite the query
     *
     * @param \Elastica\Query\AbstractQuery $query Set the query
     * @return $this
     */
    public function setFullQuery(AbstractQuery $query)
    {
        $this->_queryParts['query'] = $query;

        return $this;
    }

    /**
     * Add an aggregation to the elastic query object
     *
     * @param  array|\Elastica\Aggregation\AbstractAggregation $aggregation One or multiple facets
     * @return $this
     */
    public function aggregate($aggregation)
    {
        if (is_array($aggregation)) {
            foreach ($aggregation as $aggregationItem) {
                $this->aggregate($aggregationItem);
            }
        } else {
            $this->_queryParts['aggregations'][] = $aggregation;
        }

        return $this;
    }

    /**
     * Set or get the search options
     *
     * @param  null|array $options An array of additional search options
     * @return $this|array
     */
    public function searchOptions(?array $options = null)
    {
        if ($options === null) {
            return $this->_searchOptions;
        }

        $this->_searchOptions = $options;

        return $this;
    }

    /**
     * Auxiliary function used to parse conditions into bool query and store them in a _queryParts
     * variable.
     *
     * @param string $partType The name of the part in which the bool query will be stored
     * @param array|callable|\Elastica\Query\AbstractQuery $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous query.
     * @param string $type The method to use for appending the conditions to the Query
     * @return $this
     */
    protected function _buildBoolQuery($partType, $conditions, $overwrite, $type = 'addMust')
    {
        if ($this->_queryParts[$partType] === null || $overwrite) {
            $this->_queryParts[$partType] = new ElasticaQuery\BoolQuery();
        }

        if ($conditions instanceof AbstractQuery) {
            $this->_queryParts[$partType]->{$type}($conditions);

            return $this;
        }

        if (is_callable($conditions)) {
            $conditions = $conditions(new QueryBuilder(), $this->_queryParts[$partType], $this);
        }

        if ($conditions === null) {
            return $this;
        }

        if (is_array($conditions)) {
            $conditions = (new QueryBuilder())->parse($conditions);
            array_map([$this->_queryParts[$partType], $type], $conditions);

            return $this;
        }

        $this->_queryParts[$partType]->{$type}($conditions);

        return $this;
    }

    /**
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once. The option array accepts:
     *
     * - fields: Maps to the select method
     * - conditions: Maps to the where method
     * - order: Maps to the order method
     * - limit: Maps to the limit method
     * - offset: Maps to the offset method
     * - page: Maps to the page method
     *
     * ### Example:
     *
     * ```
     * $query->applyOptions([
     *   'fields' => ['id', 'name'],
     *   'conditions' => [
     *     'created >=' => '2013-01-01'
     *   ],
     *   'limit' => 10
     * ]);
     * ```
     *
     * Is equivalent to:
     *
     * ```
     *  $query
     *  ->select(['id', 'name'])
     *  ->where(['created >=' => '2013-01-01'])
     *  ->limit(10)
     * ```
     *
     * @param array $options list of query clauses to apply new parts to.
     * @return $this
     */
    public function applyOptions(array $options)
    {
        $valid = [
            'fields' => 'select',
            'conditions' => 'where',
            'order' => 'order',
            'limit' => 'limit',
            'offset' => 'offset',
            'page' => 'page',
        ];

        ksort($options);
        foreach ($options as $option => $values) {
            if (isset($valid[$option]) && isset($values)) {
                $this->{$valid[$option]}($values);
            } else {
                $this->_options[$option] = $values;
            }
        }

        return $this;
    }

    /**
     * Set the highlight options for the query.
     *
     * @param array $highlight The highlight options to use.
     * @return $this
     */
    public function highlight(array $highlight)
    {
        $this->_queryParts['highlight'] = $highlight;

        return $this;
    }

    /**
     * Sets the minim score the results should have in order to be
     * returned in the resultset
     *
     * @param float $score The minimum score to observe
     * @return $this
     */
    public function withMinScore($score)
    {
        $this->_elasticQuery->setMinScore($score);

        return $this;
    }

    /**
     * Executes the query.
     *
     * @return \Cake\ElasticSearch\ResultSet The results of the query
     */
    protected function _execute(): ResultSetInterface
    {
        $connection = $this->_repository->getConnection();
        $index = $this->_repository->getName();
        $esIndex = $connection->getIndex($index);

        $query = $this->compileQuery();

        return new ResultSet($esIndex->search($query, $this->_searchOptions), $this);
    }

    /**
     * Compile the Elasticsearch query.
     *
     * @return string The Elasticsearch query.
     */
    public function compileQuery()
    {
        if ($this->_queryParts['fields']) {
            $this->_elasticQuery->setSource($this->_queryParts['fields']);
        }

        if (isset($this->_queryParts['limit'])) {
            $this->_elasticQuery->setSize($this->_queryParts['limit']);
        }

        if (isset($this->_queryParts['offset'])) {
            $this->_elasticQuery->setFrom($this->_queryParts['offset']);
        }

        if ($this->_queryParts['order']) {
            $this->_elasticQuery->setSort($this->_queryParts['order']);
        }

        if ($this->_queryParts['highlight']) {
            $this->_elasticQuery->setHighlight($this->_queryParts['highlight']);
        }

        if ($this->_queryParts['aggregations']) {
            foreach ($this->_queryParts['aggregations'] as $aggregation) {
                $this->_elasticQuery->addAggregation($aggregation);
            }
        }

        if ($this->_queryParts['query'] === null) {
            $this->_queryParts['query'] = new ElasticaQuery\BoolQuery();
        }

        $query = clone $this->_queryParts['query'];

        if ($query instanceof ElasticaQuery\BoolQuery && $this->_queryParts['filter'] !== null) {
            $query->addFilter($this->_queryParts['filter']);
        }

        if ($this->_queryParts['postFilter'] !== null) {
            $this->_elasticQuery->setPostFilter($this->_queryParts['postFilter']);
        }

        $this->_elasticQuery->setQuery($query);

        return $this->_elasticQuery;
    }

    /**
     * @inheritDoc
     */
    public function aliasField(string $field, ?string $alias = null): array
    {
        return [$field => $field];
    }

    /**
     * @inheritDoc
     */
    public function aliasFields(array $fields, ?string $defaultAlias = null): array
    {
        return array_map([$this, 'aliasField', $fields]);
    }

    /**
     * Returns the total amount of hits for the query
     *
     * @return int
     */
    public function count(): int
    {
        $connection = $this->_repository->getConnection();
        $index = $this->_repository->getName();
        $esIndex = $connection->getIndex($index);

        $query = clone $this->compileQuery();
        $query->setSize(0);
        $query->setSource(false);

        return $esIndex->search($query)->getTotalHits();
    }
}
