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

use Cake\Datasource\QueryTrait;
use Cake\ElasticSearch\ResultSet;
use Cake\ElasticSearch\Type;
use Elastica\Filter\AbstractFilter;
use Elastica\Filter\Bool as BoolFilter;
use Elastica\Query as ElasticaQuery;
use Elastica\Query\Filtered as FilteredQuery;
use IteratorAggregate;

class Query implements IteratorAggregate
{

    use QueryTrait;

    /**
     * Indicates that the operation should append to the list
     *
     * @var integer
     */
    const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var integer
     */
    const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var boolean
     */
    const OVERWRITE = true;

    protected $_elasticQuery;

    protected $_parts = [
        'fields' => [],
        'preFilter' => null,
        'postFilter' => null,
        'order' => [],
        'limit' => null
    ];

    /**
     * Internal state to track whether or not the query has been modified.
     *
     * @var bool
     */
    protected $_dirty = false;

    public function __construct(Type $repository)
    {
        $this->repository($repository);
        $this->_elasticQuery = new ElasticaQuery;
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
    * @param array $order The list of fields to select from _source.
    * @param bool $overwrite Whether or not to replace previous selections.
    * @return $this
    */
    public function select(array $fields, $overwrite = false)
    {
        if (!$overwrite) {
            $fields = array_merge($this->_parts['fields'], $fields);
        }
        $this->_parts['fields'] = $fields;
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
        $this->_parts['limit'] = (int)$limit;
        return $this;
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
                $this->_parts['order'] = $order;
                return $this;
            }
            $this->_parts['order'] = array_merge($order, $this->_parts['order']);
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
            $order = array_merge($this->_parts['order'], $order);
        }

        $this->_parts['order'] = $order;
        return $this;
    }

    /**
    * Sets the filter to use in a FilteredQuery object. Filters added using this method
    * will be stacked on a bool filter and applied to the filter part of a filtered query.
    *
    * There are several way in which you can use this method. The easiest one is by passing
    * a simple array of conditions:
    *
    * {{{
    *   // Generates a {"term": {"name": "jose"}} json filter
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
    * filters in the `Cake\ElasticSearch\FilterBuilder::parse()` method documentation.
    *
    * Additionally, it is possible to use a closure as first argument. The closure will receive
    * a FilterBuilder instance, that you can use for creating arbitrary filter combinations:
    *
    * {{{
    *   $query->where(function ($builder) {
    *    return $builder->and($builder->between('age', 10, 20), $builder->missing('name'));
    *   });
    * }}}
    *
    * Finally, you can pass any already built filters as first argument:
    *
    * {{{
    *   $query->where(new Elastica\Filter\Term('name.first', 'jose'));
    * }}{
    *
    * @param array|callable|Elastica\Filter\AbstractFilter $conditions The list of conditions.
    * @param bool $overwrite Whether or not to replace previous filters.
    * @return $this
    * @see Cake\ElasticSearch\FilterBuilder
    */
    public function where($conditions, $overwrite = false)
    {
        return $this->_buildFilter('preFilter', $conditions, $overwrite);
    }

    /**
    * Sets the filter to use in the post_filter object. Filters added using this method
    * will be stacked on a bool filter.
    *
    * This method can be used in the same way the `where()` method is used. Please refer to
    * its documentation for more details.
    *
    * @param array|callable|Elastica\Filter\AbstractFilter $conditions The list of conditions.
    * @param bool $overwrite Whether or not to replace previous filters.
    * @return $this
    * @see Cake\ElasticSearch\Query::where()
    */
    public function postFilter($conditions, $overwrite = false)
    {
        return $this->_buildFilter('postFilter', $conditions, $overwrite);
    }

    protected function _buildFilter($type, $conditions, $overwrite)
    {
        if ($this->_parts[$type] === null || $overwrite) {
            $this->_parts[$type] = new BoolFilter;
        }

        if ($conditions instanceof AbstractFilter) {
            $this->_parts[$type]->addMust($conditions);
            return $this;
        }

        if (is_callable($conditions)) {
            $conditions = $conditions(new FilterBuilder, $this->_parts[$type], $this);
        }

        if ($conditions === null) {
            return $this;
        }

        if (is_array($conditions)) {
            $conditions = (new FilterBuilder)->parse($conditions);
            array_map([$this->_parts[$type], 'addMust'], $conditions);
            return $this;
        }

        $this->_parts[$type]->addMust($conditions);
        return $this;
    }

    public function applyOptions(array $options)
    {
        $this->_options = $options + $this->_options;
    }

    protected function _execute()
    {
        $connection = $this->_repository->connection();
        $name = $this->_repository->name();
        $type = $connection->getIndex()->getType($name);

        $query = $this->compileQuery();
        return new ResultSet($type->search($query), $this);
    }

    public function compileQuery()
    {
        if ($this->_parts['fields']) {
            $this->_elasticQuery->setSource($this->_parts['fields']);
        }

        if ($this->_parts['limit']) {
            $this->_elasticQuery->setSize($this->_parts['limit']);
        }

        if ($this->_parts['order']) {
            $this->_elasticQuery->setSort($this->_parts['order']);
        }

        if ($this->_parts['preFilter'] !== null) {
            $filteredQuery = new FilteredQuery();
            $filteredQuery->setFilter($this->_parts['preFilter']);
            $this->_elasticQuery->setQuery($filteredQuery);
        }

        if ($this->_parts['postFilter'] !== null) {
            $this->_elasticQuery->setPostFilter($this->_parts['postFilter']);
        }

        return $this->_elasticQuery;
    }
}
