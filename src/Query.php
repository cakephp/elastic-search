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

use Cake\Collection\Iterator\MapReduce;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryCacher;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetInterface;
use Closure;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Collapse;
use Elastica\Query as ElasticaQuery;
use Elastica\Query\AbstractQuery;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\SimpleCache\CacheInterface;
use Traversable;

class Query implements IteratorAggregate, QueryInterface
{
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
    protected ElasticaQuery $_elasticQuery;

    /**
     * The various query builder parts that will
     * be transferred to the elastica query.
     *
     * @var array
     */
    protected array $_queryParts = [
        'fields' => [],
        'limit' => null,
        'offset' => null,
        'order' => [],
        'highlight' => null,
        'collapse' => null,
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
    protected bool $_dirty = false;

    /**
     * Additional options for Elastica\Index::search()
     *
     * @see \Elastica\Search::OPTION_SEARCH_* constants
     * @var array
     */
    protected array $_searchOptions = [];

    /**
     * Instance of a repository object this query is bound to.
     *
     * @var \Cake\ElasticSearch\Index
     */
    protected Index $_repository;

    /**
     * A ResultSet.
     *
     * When set, query execution will be bypassed.
     *
     * @var iterable|null
     * @see \Cake\Datasource\QueryTrait::setResult()
     */
    protected ?iterable $_results = null;

    /**
     * List of map-reduce routines that should be applied over the query
     * result
     *
     * @var array
     */
    protected array $_mapReduce = [];

    /**
     * List of formatter classes or callbacks that will post-process the
     * results when fetched
     *
     * @var array<\Closure>
     */
    protected array $_formatters = [];

    /**
     * A query cacher instance if this query has caching enabled.
     *
     * @var \Cake\Datasource\QueryCacher|null
     */
    protected ?QueryCacher $_cache = null;

    /**
     * Holds any custom options passed using applyOptions that could not be processed
     * by any method in this class.
     *
     * @var array
     */
    protected array $_options = [];

    /**
     * Whether the query is standalone or the product of an eager load operation.
     *
     * @var bool
     */
    protected bool $_eagerLoaded = false;

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
     * @param \Closure|array|string|float|int $fields The list of fields to select from _source.
     * @param bool $overwrite Whether or not to replace previous selections.
     * @return $this
     */
    public function select(Closure|array|string|int|float $fields, bool $overwrite = false)
    {
        if (!$overwrite) {
            $fields = array_merge($this->_queryParts['fields'], $fields);
        }
        $this->_queryParts['fields'] = $fields;

        return $this;
    }

    /**
     * Sets the maximum number of results to return for this query.
     * This sets the `size` option for the Elasticsearch query.
     *
     * @param ?int $limit The number of documents to return.
     * @return $this
     */
    public function limit(?int $limit)
    {
        $this->_queryParts['limit'] = (int)$limit;

        return $this;
    }

    /**
     * Sets the number of records that should be skipped from the original result set
     * This is commonly used for paginating large results. Accepts an integer.
     *
     * @param ?int $num The number of records to be skipped
     * @return $this
     */
    public function offset(?int $num)
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
    public function clause(string $name): mixed
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
     * @param \Closure|array|string $order The sorting order to use.
     * @param bool $overwrite Whether or not to replace previous sorting.
     * @return $this
     */
    public function order(Closure|array|string $order, bool $overwrite = false)
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
     * @return static
     */
    public function find(string $finder = 'all', array $options = []): static
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
     * You can have as many conditions in the array as you'd like, Operators are also allowed
     * in the field side of the array:
     *
     * {{{
     *   $query->where(['name' => 'jose', 'age >' => 30, 'interests in' => ['php', 'cake']);
     * }}}
     *
     * You can read about the available operators and how they translate to Elasticsearch
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
     * @param \Elastica\Query\AbstractQuery|\Closure|array|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     * @see \Cake\ElasticSearch\QueryBuilder
     */
    public function where(
        Closure|array|string|AbstractQuery|null $conditions = null,
        array $types = [],
        bool $overwrite = false
    ) {
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
     * @param \Elastica\Query\AbstractQuery|callable|array|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @see \Cake\ElasticSearch\Query::where()
     * @see \Cake\ElasticSearch\QueryBuilder
     * @return $this
     */
    public function andWhere(array|callable|AbstractQuery|null $conditions, array $types = [])
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
     * @param \Elastica\Query\AbstractQuery|callable|array $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     */
    public function queryMust(array|callable|AbstractQuery $conditions, bool $overwrite = false)
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
     * @param \Elastica\Query\AbstractQuery|callable|array $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     */
    public function queryShould(array|callable|AbstractQuery $conditions, bool $overwrite = false)
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
     * @param \Elastica\Query\AbstractQuery|callable|array $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous filters.
     * @return $this
     * @see \Cake\ElasticSearch\Query::where()
     */
    public function postFilter(array|callable|AbstractQuery $conditions, bool $overwrite = false)
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
     * Add collapse to the elastic query object
     *
     * @param \Elastica\Collapse|string $collapse Collapse field or elastic collapse object
     * @return $this
     */
    public function collapse(Collapse|string $collapse)
    {
        if (is_string($collapse)) {
            $collapse = (new Collapse())->setFieldname($collapse);
        }

        $this->_queryParts['collapse'] = $collapse;

        return $this;
    }

    /**
     * Add an aggregation to the elastic query object
     *
     * @param \Elastica\Aggregation\AbstractAggregation|array $aggregation One or multiple facets
     * @return $this
     */
    public function aggregate(AbstractAggregation|array $aggregation)
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
     * @param array|null $options An array of additional search options
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
     * @param \Elastica\Query\AbstractQuery|callable|array $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous query.
     * @param string $type The method to use for appending the conditions to the Query
     * @return $this
     */
    protected function _buildBoolQuery(
        string $partType,
        AbstractQuery|callable|array $conditions,
        bool $overwrite,
        string $type = 'addMust'
    ) {
        if (!isset($this->_queryParts[$partType]) || $overwrite) {
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
    public function withMinScore(float $score)
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
     * @return \Cake\ElasticSearch\Elastica\Query The Elasticsearch query.
     */
    public function compileQuery(): ElasticaQuery
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

        if ($this->_queryParts['collapse']) {
            $this->_elasticQuery->setCollapse($this->_queryParts['collapse']);
        }

        if ($this->_queryParts['aggregations']) {
            foreach ($this->_queryParts['aggregations'] as $aggregation) {
                $this->_elasticQuery->addAggregation($aggregation);
            }
        }

        if (!isset($this->_queryParts['query'])) {
            $this->_queryParts['query'] = new ElasticaQuery\BoolQuery();
        }

        $query = clone $this->_queryParts['query'];

        if ($query instanceof ElasticaQuery\BoolQuery && isset($this->_queryParts['filter'])) {
            $query->addFilter($this->_queryParts['filter']);
        }

        if (isset($this->_queryParts['postFilter'])) {
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

    /**
     * Set the default repository object that will be used by this query.
     *
     * @param \Cake\Datasource\RepositoryInterface $repository The default repository object to use.
     * @return $this
     */
    public function repository(RepositoryInterface $repository)
    {
        assert($repository instanceof Index, 'ElasticSearch\Query requires an Index subclass');
        $this->_repository = $repository;

        return $this;
    }

    /**
     * Returns the default repository object that will be used by this query,
     * that is, the table that will appear in the from clause.
     *
     * @return \Cake\Datasource\RepositoryInterface
     */
    public function getRepository(): RepositoryInterface
    {
        return $this->_repository;
    }

    /**
     * Executes this query and returns a results iterator. This function is required
     * for implementing the IteratorAggregate interface and allows the query to be
     * iterated without having to call execute() manually, thus making it look like
     * a result set instead of the query itself.
     *
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->all();
    }

    /**
     * Enable result caching for this query.
     *
     * If a query has caching enabled, it will do the following when executed:
     *
     * - Check the cache for $key. If there are results no SQL will be executed.
     *   Instead the cached results will be returned.
     * - When the cached data is stale/missing the result set will be cached as the query
     *   is executed.
     *
     * ### Usage
     *
     * ```
     * // Simple string key + config
     * $query->cache('my_key', 'db_results');
     *
     * // Function to generate key.
     * $query->cache(function ($q) {
     *   $key = serialize($q->clause('select'));
     *   $key .= serialize($q->clause('where'));
     *   return md5($key);
     * });
     *
     * // Using a pre-built cache engine.
     * $query->cache('my_key', $engine);
     *
     * // Disable caching
     * $query->cache(false);
     * ```
     *
     * @param \Closure|string|false $key Either the cache key or a function to generate the cache key.
     *   When using a function, this query instance will be supplied as an argument.
     * @param \Psr\SimpleCache\CacheInterface|string $config Either the name of the cache config to use, or
     *   a cache engine instance.
     * @return $this
     */
    public function cache(Closure|string|false $key, CacheInterface|string $config = 'default')
    {
        if ($key === false) {
            $this->_cache = null;

            return $this;
        }
        $this->_cache = new QueryCacher($key, $config);

        return $this;
    }

    /**
     * Returns the current configured query `_eagerLoaded` value
     *
     * @return bool
     */
    public function isEagerLoaded(): bool
    {
        return $this->_eagerLoaded;
    }

    /**
     * Sets the query instance to be an eager loaded query. If no argument is
     * passed, the current configured query `_eagerLoaded` value is returned.
     *
     * @param bool $value Whether to eager load.
     * @return $this
     */
    public function eagerLoaded(bool $value)
    {
        $this->_eagerLoaded = $value;

        return $this;
    }

    /**
     * Fetch the results for this query.
     *
     * Will return either the results set through setResult(), or execute this query
     * and return the ResultSetDecorator object ready for streaming of results.
     *
     * ResultSetDecorator is a traversable object that implements the methods found
     * on Cake\Collection\Collection.
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    public function all(): ResultSetInterface
    {
        if ($this->_results !== null) {
            if (!($this->_results instanceof ResultSetInterface)) {
                $this->_results = $this->decorateResults($this->_results);
            }

            return $this->_results;
        }

        $results = null;
        if ($this->_cache) {
            $results = $this->_cache->fetch($this);
        }
        if ($results === null) {
            $results = $this->decorateResults($this->_execute());
            if ($this->_cache) {
                $this->_cache->store($this, $results);
            }
        }
        $this->_results = $results;

        return $this->_results;
    }

    /**
     * Returns an array representation of the results after executing the query.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->all()->toArray();
    }

    /**
     * Register a new MapReduce routine to be executed on top of the database results
     *
     * The MapReduce routing will only be run when the query is executed and the first
     * result is attempted to be fetched.
     *
     * If the third argument is set to true, it will erase previous map reducers
     * and replace it with the arguments passed.
     *
     * @param \Closure|null $mapper The mapper function
     * @param \Closure|null $reducer The reducing function
     * @param bool $overwrite Set to true to overwrite existing map + reduce functions.
     * @return $this
     * @see \Cake\Collection\Iterator\MapReduce for details on how to use emit data to the map reducer.
     */
    public function mapReduce(?Closure $mapper = null, ?Closure $reducer = null, bool $overwrite = false)
    {
        if ($overwrite) {
            $this->_mapReduce = [];
        }
        if ($mapper === null) {
            if (!$overwrite) {
                throw new InvalidArgumentException('$mapper can be null only when $overwrite is true.');
            }

            return $this;
        }
        $this->_mapReduce[] = compact('mapper', 'reducer');

        return $this;
    }

    /**
     * Returns the list of previously registered map reduce routines.
     *
     * @return array
     */
    public function getMapReducers(): array
    {
        return $this->_mapReduce;
    }

    /**
     * Registers a new formatter callback function that is to be executed when trying
     * to fetch the results from the database.
     *
     * If the second argument is set to true, it will erase previous formatters
     * and replace them with the passed first argument.
     *
     * Callbacks are required to return an iterator object, which will be used as
     * the return value for this query's result. Formatter functions are applied
     * after all the `MapReduce` routines for this query have been executed.
     *
     * Formatting callbacks will receive two arguments, the first one being an object
     * implementing `\Cake\Collection\CollectionInterface`, that can be traversed and
     * modified at will. The second one being the query instance on which the formatter
     * callback is being applied.
     *
     * Usually the query instance received by the formatter callback is the same query
     * instance on which the callback was attached to, except for in a joined
     * association, in that case the callback will be invoked on the association source
     * side query, and it will receive that query instance instead of the one on which
     * the callback was originally attached to - see the examples below!
     *
     * ### Examples:
     *
     * Return all results from the table indexed by id:
     *
     * ```
     * $query->select(['id', 'name'])->formatResults(function ($results) {
     *     return $results->indexBy('id');
     * });
     * ```
     *
     * Add a new column to the ResultSet:
     *
     * ```
     * $query->select(['name', 'birth_date'])->formatResults(function ($results) {
     *     return $results->map(function ($row) {
     *         $row['age'] = $row['birth_date']->diff(new DateTime)->y;
     *
     *         return $row;
     *     });
     * });
     * ```
     *
     * Add a new column to the results with respect to the query's hydration configuration:
     *
     * ```
     * $query->formatResults(function ($results, $query) {
     *     return $results->map(function ($row) use ($query) {
     *         $data = [
     *             'bar' => 'baz',
     *         ];
     *
     *         if ($query->isHydrationEnabled()) {
     *             $row['foo'] = new Foo($data)
     *         } else {
     *             $row['foo'] = $data;
     *         }
     *
     *         return $row;
     *     });
     * });
     * ```
     *
     * Retaining access to the association target query instance of joined associations,
     * by inheriting the contain callback's query argument:
     *
     * ```
     * // Assuming a `Articles belongsTo Authors` association that uses the join strategy
     *
     * $articlesQuery->contain('Authors', function ($authorsQuery) {
     *     return $authorsQuery->formatResults(function ($results, $query) use ($authorsQuery) {
     *         // Here `$authorsQuery` will always be the instance
     *         // where the callback was attached to.
     *
     *         // The instance passed to the callback in the second
     *         // argument (`$query`), will be the one where the
     *         // callback is actually being applied to, in this
     *         // example that would be `$articlesQuery`.
     *
     *         // ...
     *
     *         return $results;
     *     });
     * });
     * ```
     *
     * @param \Closure|null $formatter The formatting function
     * @param int|bool $mode Whether to overwrite, append or prepend the formatter.
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function formatResults(?Closure $formatter = null, int|bool $mode = self::APPEND)
    {
        if ($mode === self::OVERWRITE) {
            $this->_formatters = [];
        }
        if ($formatter === null) {
            /** @psalm-suppress RedundantCondition */
            if ($mode !== self::OVERWRITE) {
                throw new InvalidArgumentException('$formatter can be null only when $mode is overwrite.');
            }

            return $this;
        }

        if ($mode === self::PREPEND) {
            array_unshift($this->_formatters, $formatter);

            return $this;
        }

        $this->_formatters[] = $formatter;

        return $this;
    }

    /**
     * Returns the list of previously registered format routines.
     *
     * @return array<\Closure>
     */
    public function getResultFormatters(): array
    {
        return $this->_formatters;
    }

    /**
     * Returns the first result out of executing this query, if the query has not been
     * executed before, it will set the limit clause to 1 for performance reasons.
     *
     * ### Example:
     *
     * ```
     * $singleUser = $query->select(['id', 'username'])->first();
     * ```
     *
     * @return mixed The first result from the ResultSet.
     */
    public function first(): mixed
    {
        if ($this->_dirty) {
            $this->limit(1);
        }

        return $this->all()->first();
    }

    /**
     * Get the first result from the executing query or raise an exception.
     *
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When there is no first record.
     * @return mixed The first result from the ResultSet.
     */
    public function firstOrFail(): mixed
    {
        $entity = $this->first();
        if (!$entity) {
            $table = $this->getRepository();
            throw new RecordNotFoundException(sprintf(
                'Record not found in table "%s"',
                $table->getTable()
            ));
        }

        return $entity;
    }

    /**
     * Returns an array with the custom options that were applied to this query
     * and that were not already processed by another method in this class.
     *
     * ### Example:
     *
     * ```
     *  $query->applyOptions(['doABarrelRoll' => true, 'fields' => ['id', 'name']);
     *  $query->getOptions(); // Returns ['doABarrelRoll' => true]
     * ```
     *
     * @see \Cake\Datasource\QueryInterface::applyOptions() to read about the options that will
     * be processed by this class and not returned by this function
     * @return array
     * @see applyOptions()
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * Decorates the results iterator with MapReduce routines and formatters
     *
     * @param iterable $result Original results
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function decorateResults(iterable $result): ResultSetInterface
    {
        $decorator = $this->decoratorClass();

        if (!empty($this->_mapReduce)) {
            foreach ($this->_mapReduce as $functions) {
                $result = new MapReduce($result, $functions['mapper'], $functions['reducer']);
            }
            $result = new $decorator($result);
        }

        if (!($result instanceof ResultSetInterface)) {
            $result = new $decorator($result);
        }

        if (!empty($this->_formatters)) {
            foreach ($this->_formatters as $formatter) {
                $result = $formatter($result, $this);
            }

            if (!($result instanceof ResultSetInterface)) {
                $result = new $decorator($result);
            }
        }

        return $result;
    }

    /**
     * Returns the name of the class to be used for decorating results
     *
     * @return class-string<\Cake\Datasource\ResultSetInterface>
     */
    protected function decoratorClass(): string
    {
        return ResultSetDecorator::class;
    }
}
