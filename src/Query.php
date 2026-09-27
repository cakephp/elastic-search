<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch;

use Cake\Collection\Iterator\MapReduce;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryCacher;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetDecorator;
use Cake\Datasource\ResultSetInterface;
use Closure;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Collapse;
use Elastica\Query as ElasticaQuery;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use InvalidArgumentException;
use IteratorAggregate;
use Psr\SimpleCache\CacheInterface;
use Traversable;
use function Cake\Collection\collection;

/**
 * @template TSubject of \Cake\ElasticSearch\Document|array
 * @implements \IteratorAggregate<TSubject>
 */
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
    protected ElasticaQuery $elasticQuery;

    /**
     * The various query builder parts that will
     * be transferred to the elastica query.
     */
    protected array $queryParts = [
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
        'trackTotalHits' => null,
    ];

    /**
     * Internal state to track whether or not the query has been modified.
     */
    protected bool $dirty = false;

    /**
     * Additional options for Elastica\Index::search()
     *
     * @see \Elastica\Search::OPTION_SEARCH_* constants
     */
    protected array $searchOptions = [];

    /**
     * Instance of a repository object this query is bound to.
     */
    protected Index $repository;

    /**
     * A ResultSet.
     *
     * When set, query execution will be bypassed.
     *
     * @see \Cake\Datasource\QueryTrait::setResult()
     */
    protected ?iterable $results = null;

    /**
     * List of map-reduce routines that should be applied over the query
     * result
     */
    protected array $mapReduce = [];

    /**
     * List of formatter classes or callbacks that will post-process the
     * results when fetched
     *
     * @var array<\Closure>
     */
    protected array $formatters = [];

    /**
     * A query cacher instance if this query has caching enabled.
     */
    protected ?QueryCacher $cache = null;

    /**
     * Holds any custom options passed using applyOptions that could not be processed
     * by any method in this class.
     */
    protected array $options = [];

    /**
     * Query constructor
     *
     * @param \Cake\ElasticSearch\Index $repository The type of document.
     */
    public function __construct(Index $repository)
    {
        $this->setRepository($repository);
        $this->elasticQuery = new ElasticaQuery();
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
    public function select(Closure|array|string|int|float $fields, bool $overwrite = false): static
    {
        if (!$overwrite) {
            $currentFields = $this->queryParts['fields'];
            if (!is_array($currentFields)) {
                $currentFields = [];
            }

            if (!is_array($fields)) {
                $fields = [$fields];
            }

            $fields = array_merge($currentFields, $fields);
        }

        $this->queryParts['fields'] = $fields;

        return $this;
    }

    /**
     * Sets the maximum number of results to return for this query.
     * This sets the `size` option for the Elasticsearch query.
     *
     * @param ?int $limit The number of documents to return.
     * @return $this
     */
    public function limit(?int $limit): static
    {
        $this->queryParts['limit'] = (int)$limit;

        return $this;
    }

    /**
     * Sets the number of records that should be skipped from the original result set
     * This is commonly used for paginating large results. Accepts an integer.
     *
     * @param ?int $offset The number of records to be skipped
     * @return $this
     */
    public function offset(?int $offset): static
    {
        $this->queryParts['offset'] = (int)$offset;

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
    public function page(int $num, ?int $limit = null): static
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
     */
    public function clause(string $name): mixed
    {
        return $this->queryParts[$name];
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
     * @param \Closure|array|string $fields The sorting order to use.
     * @param bool $overwrite Whether or not to replace previous sorting.
     * @return $this
     */
    public function orderBy(array|Closure|string $fields, bool $overwrite = false): static
    {
        if (is_array($fields) && is_numeric(key($fields))) {
            if ($overwrite) {
                $this->queryParts['order'] = $fields;

                return $this;
            }

            $this->queryParts['order'] = array_merge($fields, $this->queryParts['order']);

            return $this;
        }

        if (is_string($fields)) {
            $fields = [$fields => ['order' => 'desc']];
        }

        $normalizer = function ($fields, $key): array {
          // ['field' => 'asc|desc']
            if (is_string($fields)) {
                return [$key => ['order' => $fields]];
            }

            return [$key => $fields];
        };

        if (!is_iterable($fields)) {
            $fields = [$fields];
        }

        $fields = collection($fields)->map($normalizer)->toList();

        if (!$overwrite) {
            $fields = array_merge($this->queryParts['order'], $fields);
        }

        $this->queryParts['order'] = $fields;

        return $this; // [['field' => [...]], ['field2' => [...]]]
    }

    /**
     * {@inheritDoc}
     *
     * @param string $finder The finder method to use.
     * @param array $args The options for the finder.
     * @return static<TSubject> Returns a modified query.
     * @psalm-suppress MoreSpecificReturnType
     */
    public function find(string $finder, mixed ...$args): static
    {
        return $this->repository->callFinder($finder, $this, ...$args);
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
     * @param \Closure|array|string|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     * @see \Cake\ElasticSearch\QueryBuilder
     */
    public function where(
        Closure|array|string|null $conditions = null,
        array $types = [],
        bool $overwrite = false,
    ): static {
        // Convert string conditions to proper format for _buildBoolQuery
        if (is_string($conditions)) {
            $conditions = [$conditions];
        }

        return $this->buildBoolQuery('filter', $conditions, $overwrite);
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
     * @param \Elastica\Query\AbstractQuery|\Closure|array|string|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @see \Cake\ElasticSearch\Query::where()
     * @see \Cake\ElasticSearch\QueryBuilder
     * @return $this
     */
    public function andWhere(array|Closure|AbstractQuery|string|null $conditions, array $types = []): static
    {
        if (is_string($conditions)) {
            $conditions = [$conditions];
        }

        return $this->buildBoolQuery('filter', $conditions, false, 'addMust');
    }

    /**
     * Modifies the query part, taking scores in account. Queries added using this method
     * will be stacked on a bool query and applied to the `must` part of the final BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param \Elastica\Query\AbstractQuery|\Closure|array $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     */
    public function queryMust(array|Closure|AbstractQuery $conditions, bool $overwrite = false): static
    {
        return $this->buildBoolQuery('query', $conditions, $overwrite);
    }

    /**
     * Modifies the query part, taking scores in account. Queries added using this method
     * will be stacked on a bool query and applied to the `should` part of the final BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param \Elastica\Query\AbstractQuery|\Closure|array $conditions The list of conditions
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     */
    public function queryShould(array|Closure|AbstractQuery $conditions, bool $overwrite = false): static
    {
        return $this->buildBoolQuery('query', $conditions, $overwrite, 'addShould');
    }

    /**
     * Sets the query to use in the post_filter object. Filters added using this method
     * will be stacked on a BoolQuery.
     *
     * This method can be used in the same way the `where()` method is used. Please refer to
     * its documentation for more details.
     *
     * @param \Elastica\Query\AbstractQuery|\Closure|array $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous filters.
     * @return $this
     * @see \Cake\ElasticSearch\Query::where()
     */
    public function postFilter(array|Closure|AbstractQuery $conditions, bool $overwrite = false): static
    {
        return $this->buildBoolQuery('postFilter', $conditions, $overwrite);
    }

    /**
     * Method to set or overwrite the query
     *
     * @param \Elastica\Query\AbstractQuery $query Set the query
     * @return $this
     */
    public function setFullQuery(AbstractQuery $query): static
    {
        $this->queryParts['query'] = $query;

        return $this;
    }

    /**
     * Add collapse to the elastic query object
     *
     * @param \Elastica\Collapse|string $collapse Collapse field or elastic collapse object
     * @return $this
     */
    public function collapse(Collapse|string $collapse): static
    {
        if (is_string($collapse)) {
            $collapse = (new Collapse())->setFieldname($collapse);
        }

        $this->queryParts['collapse'] = $collapse;

        return $this;
    }

    /**
     * Add an aggregation to the elastic query object
     *
     * @param \Elastica\Aggregation\AbstractAggregation|array $aggregation One or multiple facets
     * @return $this
     */
    public function aggregate(AbstractAggregation|array $aggregation): static
    {
        if (is_array($aggregation)) {
            foreach ($aggregation as $aggregationItem) {
                $this->aggregate($aggregationItem);
            }
        } else {
            $this->queryParts['aggregations'][] = $aggregation;
        }

        return $this;
    }

    /**
     * Set or get the search options
     *
     * @param array|null $options An array of additional search options
     */
    public function searchOptions(?array $options = null): array|self
    {
        if ($options === null) {
            return $this->searchOptions;
        }

        $this->searchOptions = $options;

        return $this;
    }

    /**
     * Auxiliary function used to parse conditions into bool query and store them in a _queryParts
     * variable.
     *
     * @param string $partType The name of the part in which the bool query will be stored
     * @param \Elastica\Query\AbstractQuery|\Closure|array $conditions The list of conditions.
     * @param bool $overwrite Whether or not to replace previous query.
     * @param string $type The method to use for appending the conditions to the Query
     * @return $this
     */
    protected function buildBoolQuery(
        string $partType,
        AbstractQuery|Closure|array|null $conditions,
        bool $overwrite,
        string $type = 'addMust',
    ): static {
        if (!isset($this->queryParts[$partType]) || $overwrite) {
            $this->queryParts[$partType] = new BoolQuery();
        }

        if ($conditions === null) {
            return $this;
        }

        if ($conditions instanceof AbstractQuery) {
            $this->queryParts[$partType]->{$type}($conditions);

            return $this;
        }

        if ($conditions instanceof Closure) {
            $conditions = $conditions(new QueryBuilder(), $this->queryParts[$partType], $this);
        }

        if ($conditions === null) {
            return $this;
        }

        if (is_array($conditions)) {
            $conditions = (new QueryBuilder())->parse($conditions);
            if (is_array($conditions)) {
                foreach ($conditions as $condition) {
                    $this->queryParts[$partType]->{$type}($condition);
                }
            }

            return $this;
        }

        $this->queryParts[$partType]->{$type}($conditions);

        return $this;
    }

    /**
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once. The option array accepts:
     *
     * - fields: Maps to the select method
     * - conditions: Maps to the where method
     * - order: Maps to the orderBy method
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
    public function applyOptions(array $options): static
    {
        $valid = [
            'fields' => 'select',
            'conditions' => 'where',
            'order' => 'orderBy',
            'limit' => 'limit',
            'offset' => 'offset',
            'page' => 'page',
        ];

        ksort($options);
        foreach ($options as $option => $values) {
            if (isset($valid[$option]) && isset($values)) {
                $this->{$valid[$option]}($values);
            } else {
                $this->options[$option] = $values;
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
    public function highlight(array $highlight): static
    {
        $this->queryParts['highlight'] = $highlight;

        return $this;
    }

    /**
     * Sets the minim score the results should have in order to be
     * returned in the resultset
     *
     * @param float $score The minimum score to observe
     * @return $this
     */
    public function withMinScore(float $score): static
    {
        $this->elasticQuery->setMinScore($score);

        return $this;
    }

    /**
     * Sets the track_total_hits parameter for the query.
     *
     * Controls how the total number of hits should be tracked.
     * - true: Track exact total count (can be slow for large datasets)
     * - false: Don't track total hits (faster performance)
     * - int: Track up to the specified number (balance between accuracy and performance)
     * - null: Use Elasticsearch default behavior
     *
     * @param int|bool|null $trackTotalHits The track_total_hits parameter
     * @return $this
     * @throws \InvalidArgumentException When a negative integer value is provided
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-body.html#request-body-search-track-total-hits
     */
    public function trackTotalHits(int|bool|null $trackTotalHits): static
    {
        if (is_int($trackTotalHits) && $trackTotalHits < 0) {
            throw new InvalidArgumentException(
                'trackTotalHits integer value must be non-negative. Got: ' . $trackTotalHits,
            );
        }

        $this->queryParts['trackTotalHits'] = $trackTotalHits;
        $this->dirty = true;

        return $this;
    }

    /**
     * Executes the query.
     *
     * @return \Cake\ElasticSearch\ResultSet The results of the query
     */
    protected function execute(): ResultSetInterface
    {
        $connection = $this->repository->getConnection();
        $index = $this->repository->getName();
        $esIndex = $connection->getIndex($index);

        $query = $this->compileQuery();

        return new ResultSet($esIndex->search($query, $this->searchOptions), $this);
    }

    /**
     * Compile the Elasticsearch query.
     *
     * @return \Elastica\Query The Elasticsearch query.
     */
    public function compileQuery(): ElasticaQuery
    {
        if ($this->queryParts['fields']) {
            $this->elasticQuery->setSource($this->queryParts['fields']);
        }

        if (isset($this->queryParts['limit'])) {
            $this->elasticQuery->setSize($this->queryParts['limit']);
        }

        if (isset($this->queryParts['offset'])) {
            $this->elasticQuery->setFrom($this->queryParts['offset']);
        }

        if ($this->queryParts['order']) {
            $this->elasticQuery->setSort($this->queryParts['order']);
        }

        if ($this->queryParts['highlight']) {
            $this->elasticQuery->setHighlight($this->queryParts['highlight']);
        }

        if ($this->queryParts['collapse']) {
            $this->elasticQuery->setCollapse($this->queryParts['collapse']);
        }

        if ($this->queryParts['aggregations']) {
            foreach ($this->queryParts['aggregations'] as $aggregation) {
                $this->elasticQuery->addAggregation($aggregation);
            }
        }

        if ($this->queryParts['trackTotalHits'] !== null) {
            $this->elasticQuery->setTrackTotalHits($this->queryParts['trackTotalHits']);
        }

        if (!isset($this->queryParts['query'])) {
            $this->queryParts['query'] = new BoolQuery();
        }

        /** @var \Elastica\Query\AbstractQuery $query */
        $query = clone $this->queryParts['query'];

        if ($query instanceof BoolQuery && isset($this->queryParts['filter'])) {
            $query->addFilter($this->queryParts['filter']);
        }

        if (isset($this->queryParts['postFilter'])) {
            $this->elasticQuery->setPostFilter($this->queryParts['postFilter']);
        }

        $this->elasticQuery->setQuery($query);

        return $this->elasticQuery;
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
        $aliased = [];
        foreach ($fields as $alias => $field) {
            if (is_numeric($alias) && is_string($field)) {
                $aliased += $this->aliasField($field, $defaultAlias);
                continue;
            }

            $aliased[$alias] = $field;
        }

        return $aliased;
    }

    /**
     * Returns the total amount of hits for the query
     */
    public function count(): int
    {
        $connection = $this->repository->getConnection();
        $index = $this->repository->getName();
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
    public function setRepository(RepositoryInterface $repository): static
    {
        assert($repository instanceof Index, 'ElasticSearch\Query requires an Index subclass');
        $this->repository = $repository;

        return $this;
    }

    /**
     * Returns the default repository object that will be used by this query,
     * that is, the table that will appear in the from clause.
     */
    public function getRepository(): Index
    {
        return $this->repository;
    }

    /**
     * Executes this query and returns a results iterator. This function is required
     * for implementing the IteratorAggregate interface and allows the query to be
     * iterated without having to call execute() manually, thus making it look like
     * a result set instead of the query itself.
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
    public function cache(Closure|string|false $key, CacheInterface|string $config = 'default'): static
    {
        if ($key === false) {
            $this->cache = null;

            return $this;
        }

        $this->cache = new QueryCacher($key, $config);

        return $this;
    }

    /**
     * Fetch the results for this query.
     *
     * Will return either the results set through setResult(), or execute this query
     * and return the ResultSet object ready for streaming of results.
     *
     * When mapReduce or formatters are applied, the results are wrapped in a
     * ResultSetDecorator which is a traversable object that implements the methods
     * found on Cake\Collection\Collection.
     *
     * @return \Cake\ElasticSearch\ResultSet|\Cake\Datasource\ResultSetDecorator
     * @phpstan-return \Cake\Datasource\ResultSetInterface
     */
    public function all(): ResultSetInterface
    {
        if ($this->results !== null) {
            if (!($this->results instanceof ResultSetInterface)) {
                $this->results = $this->decorateResults($this->results);
            }

            return $this->results;
        }

        $results = null;
        if ($this->cache instanceof QueryCacher) {
            $results = $this->cache->fetch($this);
        }

        if ($results === null) {
            $results = $this->decorateResults($this->execute());
            if ($this->cache instanceof QueryCacher) {
                $this->cache->store($this, $results);
            }
        }

        $this->results = $results;

        return $this->results;
    }

    /**
     * Returns an array representation of the results after executing the query.
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
    public function mapReduce(?Closure $mapper = null, ?Closure $reducer = null, bool $overwrite = false): static
    {
        if ($overwrite) {
            $this->mapReduce = [];
        }

        if (!$mapper instanceof Closure) {
            if (!$overwrite) {
                throw new InvalidArgumentException('$mapper can be null only when $overwrite is true.');
            }

            return $this;
        }

        $this->mapReduce[] = ['mapper' => $mapper, 'reducer' => $reducer];

        return $this;
    }

    /**
     * Returns the list of previously registered map reduce routines.
     */
    public function getMapReducers(): array
    {
        return $this->mapReduce;
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
     * @param \Closure|null $formatter The formatting function
     * @param int|bool $mode Whether to overwrite, append or prepend the formatter.
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function formatResults(?Closure $formatter = null, int|bool $mode = self::APPEND): static
    {
        if ($mode === self::OVERWRITE) {
            $this->formatters = [];
        }

        if (!$formatter instanceof Closure) {
            if ($mode !== self::OVERWRITE) {
                throw new InvalidArgumentException('$formatter can be null only when $mode is overwrite.');
            }

            return $this;
        }

        if ($mode === self::PREPEND) {
            array_unshift($this->formatters, $formatter);

            return $this;
        }

        $this->formatters[] = $formatter;

        return $this;
    }

    /**
     * Returns the list of previously registered format routines.
     *
     * @return array<\Closure>
     */
    public function getResultFormatters(): array
    {
        return $this->formatters;
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
        if ($this->dirty) {
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
                $table->getTable(),
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
     * @see applyOptions()
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Decorates the results iterator with MapReduce routines and formatters
     *
     * @param iterable $result Original results
     */
    protected function decorateResults(iterable $result): ResultSetInterface
    {
        $decorator = $this->decoratorClass();

        if ($this->mapReduce !== []) {
            foreach ($this->mapReduce as $functions) {
                $result = new MapReduce($result, $functions['mapper'], $functions['reducer']);
            }

            $result = new $decorator($result);
        }

        if (!($result instanceof ResultSetInterface)) {
            $result = new $decorator($result);
        }

        if ($this->formatters !== []) {
            foreach ($this->formatters as $formatter) {
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
