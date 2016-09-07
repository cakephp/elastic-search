<?php

namespace Cake\ElasticSearch;

use Elastica\Filter;
use Elastica\Filter\AbstractFilter;
use Elastica\Query\AbstractQuery;

class FilterBuilder
{

    /**
     * Returns a Range filter object setup to filter documents having the field between
     * a `from` and a `to` value
     *
     * @param string $field The field to filter by.
     * @param mixed $from The lower bound value.
     * @param mixed $to The upper bound value.
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function between($field, $from, $to)
    {
        return $this->range($field, [
            'gte' => $from,
            'lte' => $to
        ]);
    }

    /**
     * Returns a bool filter that can be chained with the `addMust()`, `addShould()`
     * and `addMustNot()` methods.
     *
     * @return \Elastica\Filter\BoolFilter
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-filter.html
     */
    public function bool()
    {
        return new Filter\BoolFilter();
    }

    /**
     * Returns an Exists filter object setup to filter documents having a property present
     * or not set to null.
     *
     * @param string $field The field to check for existance.
     * @return \Elastica\Filter\Exists
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-filter.html
     */
    public function exists($field)
    {
        return new Filter\Exists($field);
    }

    /**
     * Returns a GeoBoundingBox filter object setup to filter documents having a property
     * bound by two coordinates.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoBoundingBox('location', [40.73, -74.1], [40.01, -71.12]);
     *
     *    $filter = $builder->geoBoundingBox(
     *        'location',
     *        ['lat => 40.73, 'lon' => -74.1],
     *        ['lat => 40.01, 'lon' => -71.12]
     *    );
     *
     *    $filter = $builder->geoBoundingBox('location', 'dr5r9ydj2y73', 'drj7teegpus6');
     * }}}
     *
     * @param string $field The field to compare.
     * @param array|string $topLeft The top left coordinate.
     * @param array|string $bottomRight The bottom right coordinate.
     * @return \Elastica\Filter\GeoBoundingBox
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-bounding-box-filter.html
     */
    public function geoBoundingBox($field, $topLeft, $bottomRight)
    {
        return new Filter\GeoBoundingBox($field, [$topLeft, $bottomRight]);
    }

    /**
     * Returns an GeoDistance filter object setup to filter documents having a property
     * in the radius distance of a coordinate.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoDistance('location', [40.73, -74.1], '10km');
     *
     *    $filter = $builder->geoBoundingBox('location', 'dr5r9ydj2y73', '5km');
     * }}}
     *
     * @param string $field The field to compare.
     * @param array|string $location The coordinate from which to compare.
     * @param string $distance The distance radius.
     * @return \Elastica\Filter\GeoDistance
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-distance-range-filter.html
     */
    public function geoDistance($field, $location, $distance)
    {
        return new Filter\GeoDistance($field, $location, $distance);
    }

    /**
     * Returns an GeoDistanceRange filter object setup to filter documents having a property
     * in between two distance radius from a location coordinate.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoDistanceRange('location', [40.73, -74.1], '10km', '20km');
     *
     *    $filter = $builder->geoDistanceRange('location', 'dr5r9ydj2y73', '5km', '10km');
     * }}}
     *
     * @param string $field The field to compare.
     * @param array|string $location The coordinate from which to compare.
     * @param string $from The initial distance radius.
     * @param string $to The ending distance radius.
     * @return \Elastica\Filter\GeoDistanceRange
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-distance-filter.html
     */
    public function geoDistanceRange($field, $location, $from, $to)
    {
        return new Filter\GeoDistanceRange($field, $location, [
            'gte' => $from,
            'lte' => $to
        ]);
    }

    /**
     * Returns an GeoPolygon filter object setup to filter documents having a property
     * enclosed in the polygon induced by the passed geo points.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoPolygon('location', [
     *        ['lat' => 40, 'lon' => -70],
     *        ['lat' => 30, 'lon' => -80],
     *        ['lat' => 20, 'lon' => -90],
     *    ]);
     *
     *    $filter = $builder->geoPolygon('location', [
     *        'drn5x1g8cu2y',
     *        ['lat' => 30, 'lon' => -80],
     *        '20, -90',
     *    ]);
     * }}}
     *
     * @param string $field The field to compare.
     * @param array $geoPoints List of geo points that form the polygon
     * @return \Elastica\Filter\GeoPolygon
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-polygon-filter.html
     */
    public function geoPolygon($field, array $geoPoints)
    {
        return new Filter\GeoPolygon($field, $geoPoints);
    }

    /**
     * Returns an GeoShapeProvided filter object setup to filter documents having a property
     * enclosed in the specified geometrical shape type.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoShape('location', [[13.0, 53.0], [14.0, 52.0]], 'envelope');
     *
     *    $filter = $builder->geoShape('location', [
     *        [[-77.03653, 38.897676], [-77.009051, 38.889939]],
     *        'linestring'
     *    ]);
     * }}}
     *
     * You can read about the supported shapes and how they are created here:
     * http://www.elastic.co/guide/en/elasticsearch/reference/1.x/mapping-geo-shape-type.html
     *
     * @param string $field The field to compare.
     * @param array $geoPoints List of geo points that form the shape.
     * @param string $type The shape type to use (envelope, linestring, polygon, multipolygon...)
     * @return \Elastica\Filter\GeoShapeProvided
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-filter.html
     */
    public function geoShape($field, array $geoPoints, $type = 'envelope')
    {
        return new Filter\GeoShapeProvided($field, $geoPoints, $type);
    }

    /**
     * Returns an GeoShapePreIndex filter object setup to filter documents having a property
     * enclosed in the specified geometrical shape type.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoShapeIndex('location', 'DEU', 'countries', 'shapes', 'location');
     * }}}
     *
     * @param string $field The field to compare.
     * @param string $id The ID of the document containing the pre-indexed shape.
     * @param string $type Index type where the pre-indexed shape is.
     * @param string $index Name of the index where the pre-indexed shape is.
     * @param string $path The field specified as path containing the pre-indexed shape.
     * @return \Elastica\Filter\GeoShapePreIndexed
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-filter.html
     */
    public function geoShapeIndex($field, $id, $type, $index = 'shapes', $path = 'shape')
    {
        return new Filter\GeoShapePreIndexed($field, $id, $type, $index, $path);
    }

    /**
     * Returns an GeohashCell filter object setup to filter documents having a property
     * enclosed inside the specified geohash in teh give precision.
     *
     * ### Example:
     *
     * {{{
     *    $filter = $builder->geoHashCell('location', [40, -70], 3);
     * }}}
     *
     * @param string $field The field to compare.
     * @param string|array $location Location as coordinates array or geohash string.
     * @param int|string $precision Length of geohash prefix or distance (3, or "50m")
     * @param bool $neighbors If true, filters cells next to the given cell.
     * @return \Elastica\Filter\GeohashCell
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geohash-cell-filter.html
     */
    public function geoHashCell($field, $location, $precision = -1, $neighbors = false)
    {
        return new Filter\GeohashCell($field, $location, $precision, $neighbors);
    }

    /**
     * Returns a Range filter object setup to filter documents having the field
     * greater than the provided value.
     *
     * @param string $field The field to filter by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function gt($field, $value)
    {
        return $this->range($field, ['gt' => $value]);
    }

    /**
     * Returns a Range filter object setup to filter documents having the field
     * greater than or equal the provided value.
     *
     * @param string $field The field to filter by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function gte($field, $value)
    {
        return $this->range($field, ['gte' => $value]);
    }

    /**
     * Accepts a query and the child type to run against, and results in parent
     * documents that have child docs matching the query.
     *
     * @param string|\Elastica\Query|\Elastica\Filter\AbstractFilter $query The filtering conditions.
     * @param string $type The child type to query against.
     * @return \Elastica\Filter\HasChild
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-child-filter.html
     */
    public function hasChild($query, $type)
    {
        return new Filter\HasChild($query, $type);
    }

    /**
     * Filters by child documents having parent documents matching the query
     *
     * @param string|\Elastica\Query|\Elastica\Filter\AbstractFilter $query The filtering conditions.
     * @param string $type The parent type to query against.
     * @return \Elastica\Filter\HasParent
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-parent-filter.html
     */
    public function hasParent($query, $type)
    {
        return new Filter\HasParent($query, $type);
    }

    /**
     * Filters documents that only have the provided ids.
     *
     * @param array $ids The list of ids to filter by.
     * @param string|array $type A single or multiple types in which the ids should be searched.
     * @return \Elastica\Filter\Ids
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-ids-filter.html
     */
    public function ids(array $ids = [], $type = null)
    {
        return new Filter\Ids($type, $ids);
    }

    /**
     * The indices filter can be used when executed across multiple indices, allowing you to have a filter
     * that is only applied when executed on an index matching a specific list of indices, and another
     * filter that executes when it is executed on an index that does not match the listed indices.
     *
     * ### Example:
     *
     * {{{
     *    $builder->indices(
     *       ['index1', 'index2'],
     *       $builder->term('user', 'jhon'),
     *       $builder->term('tag', 'wow')
     *    );
     * }}}
     *
     * @param array $indices The indices where to apply the filter.
     * @param \Elastica\Filter\AbstractFilter $match Filter which will be applied to docs
     * in the specified indices.
     * @param \Elastica\Filter\AbstractFilter $noMatch Filter to apply to documents not present
     * in the specified indices.
     * @return \Elastica\Filter\Indices
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-indices-filter.html
     */
    public function indices(array $indices, AbstractFilter $match, AbstractFilter $noMatch)
    {
        return (new Filter\Indices($match, $indices))->setNoMatchFilter($noMatch);
    }

    /**
     * Limits the number of documents (per shard) to execute on.
     *
     * @param int $limit The maximum number of documents to filter.
     * @return \Elastica\Filter\Limit
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-limit-filter.html
     */
    public function limit($limit)
    {
        return new Filter\Limit((int)$limit);
    }

    /**
     * A filter that returns all documents.
     *
     * @return \Elastica\Filter\MatchAll
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-filter.html
     */
    public function matchAll()
    {
        return new Filter\MatchAll();
    }

    /**
     * Returns a Range filter object setup to filter documents having the field
     * smaller than the provided value.
     *
     * @param string $field The field to filter by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function lt($field, $value)
    {
        return $this->range($field, ['lt' => $value]);
    }

    /**
     * Returns a Range filter object setup to filter documents having the field
     * smaller or equals than the provided value.
     *
     * @param string $field The field to filter by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function lte($field, $value)
    {
        return $this->range($field, ['lte' => $value]);
    }

    /**
     * Returns a Missing filter object setup to filter documents not having a property present or
     * not null.
     *
     * @param string $field The field to check for existance.
     * @return \Elastica\Filter\Missing
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-filter.html
     */
    public function missing($field = '')
    {
        return new Filter\Missing($field);
    }

    /**
     * Returns a Nested filter object setup to filter sub documents by a path.
     *
     * ### Example:
     *
     * {{{
     *    $builder->nested('comments', $builder->term('author', 'mark'));
     * }}}
     *
     * Or using a query as filter:
     *
     * {{{
     *    $builder->nested('comments', new \Elastica\Query\SimpleQueryString('awesome'));
     * }}}
     *
     * @param string $path A dot separated string denoting the path to the property to filter.
     * @param \Elastica\Query\AbstractQuery|\Elastica\Filter\AbstractFilter $filter The filtering conditions.
     * @return \Elastica\Filter\Nested
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-filter.html
     */
    public function nested($path, $filter)
    {
        $nested = new Filter\Nested();
        $nested->setPath($path);

        if ($filter instanceof AbstractFilter) {
            $nested->setFilter($filter);
        }

        if ($filter instanceof AbstractQuery) {
            $nested->setQuery($filter);
        }

        return $nested;
    }

    /**
     * Returns a BoolNot filter that is typically ussed to negate another filter expression
     *
     * @param \Elastica\Filter\AbstractFilter $filter The filter to negate
     * @return \Elastica\Filter\BoolNot
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-not-filter.html
     */
    public function not($filter)
    {
        return new Filter\BoolNot($filter);
    }

    /**
     * Returns a Prefix filter to filter documents that have fields containing terms with
     * a specified prefix
     *
     * @param string $field The field to filter by.
     * @param string $prefix The prefix to check for.
     * @return \Elastica\Filter\Prefix
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-prefix-filter.html
     */
    public function prefix($field, $prefix)
    {
        return new Filter\Prefix($field, $prefix);
    }

    /**
     * Returns a Query filter that Wraps any query to be used as a filter.
     *
     * ### Example:
     *
     * {{{
     *  $builder->query(new \Elastica\Query\SimpleQueryString('awesome OR great'));
     * }}}
     *
     * @param array|\Elastica\Query\AbstractQuery $query The Query to wrap as a filter
     * @return \Elastica\Filter\Query
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-filter.html
     */
    public function query($query)
    {
        return new Filter\Query($query);
    }

    /**
     * Returns a Range filter object setup to filter documents having the field
     * greater than the provided values.
     *
     * The $args array accepts the following keys:
     *
     * - gte: greater than or equal
     * - gt: greater than
     * - lte: less than or equal
     * - lt: less than
     *
     * @param string $field The field to filter by.
     * @param array $args An array describing the search range
     * @return \Elastica\Filter\Range
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-filter.html
     */
    public function range($field, array $args)
    {
        return new Filter\Range($field, $args);
    }

    /**
     * Returns a Regexp filter to filter documents based on a regular expression.
     *
     * ### Example:
     *
     * {{{
     *  $builder->regexp('name.first', 'ma.*');
     * }}}
     *
     * @param string $field The field to filter by.
     * @param string $regexp The regular expression.
     * @param array $options Regultar expression flags or options.
     * @return \Elastica\Filter\Regexp
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-regexp-filter.html
     */
    public function regexp($field, $regexp, array $options = [])
    {
        return new Filter\Regexp($field, $regexp, $options);
    }

    /**
     * Returns a Script filter object that allows to filter based on the return value of a script.
     *
     * ### Example:
     *
     * {{{
     *  $builder->script("doc['price'].value > 1");
     * }}}
     *
     * @param string $script The script.
     * @return \Elastica\Filter\Regexp
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-regexp-filter.html
     */
    public function script($script)
    {
        return new Filter\Script($script);
    }

    /**
     * Returns a Term filter object that filters documents that have fields containing a term.
     *
     * ### Example:
     *
     * {{{
     *  $builder->term('user.name', 'jose');
     * }}}
     *
     * @param string $field The field to filter by.
     * @param string $value The term to find in field.
     * @return \Elastica\Filter\Term
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
     */
    public function term($field, $value)
    {
        return new Filter\Term([$field => $value]);
    }

    /**
     * Returns a Terms filter object that filters documents that have fields containing some terms.
     *
     * ### Example:
     *
     * {{{
     *  $builder->terms('user.name', ['jose', 'mark']);
     * }}}
     *
     * @param string $field The field to filter by.
     * @param array $values The list of terms to find in field.
     * @return \Elastica\Filter\Terms
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-filter.html
     */
    public function terms($field, $values)
    {
        return new Filter\Terms($field, $values);
    }

    /**
     * Returns a Type filter object that filters documents matching the provided document/mapping type.
     *
     * ### Example:
     *
     * {{{
     *  $builder->type('products');
     * }}}
     *
     * @param string $type The type name
     * @return \Elastica\Filter\Type
     * @see http://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-type-filter.html
     */
    public function type($type)
    {
        return new Filter\Type($type);
    }

    /**
     * Combines all the passed arguments in a single boolean filter
     * using the "must" clause.
     *
     * ### Example:
     *
     * {{{
     *  $bool = $builder->and(
     *     $builder->terms('tags', ['cool', 'stuff']),
     *     $builder->exists('comments')
     *  );
     * }}}
     *
     * @return \Elastica\Filter\BoolFilter
     */
    // @codingStandardsIgnoreStart
    public function and_()
    {
        // @codingStandardsIgnoreEnd
        $filters = func_get_args();
        $bool = $this->bool();

        foreach ($filters as $k => $filter) {
            if ($filter instanceof Filter\BoolFilter) {
                $bool = $filter;
                unset($filters[$k]);
                break;
            }
        }

        foreach ($filters as $filter) {
            $bool->addMust($filter);
        }

        return $bool;
    }

    /**
     * Combines all the passed arguments in a single BoolOr filter.
     *
     * ### Example:
     *
     * {{{
     *  $bool = $builder->or(
     *     $builder->missing('tags'),
     *     $builder->exists('comments')
     *  );
     * }}}
     *
     * @return \Elastica\Filter\BoolOr
     */
    // @codingStandardsIgnoreStart
    public function or_()
    {
        // @codingStandardsIgnoreEnd
        $filters = func_get_args();
        $or = new Filter\BoolOr();

        foreach ($filters as $filter) {
            $or->addFilter($filter);
        }

        return $or;
    }

    /**
     * Helps calling the `and()` and `or()` methods transparently.
     *
     * @param string $method The method name.
     * @param array $args The argumemts to pass to the method.
     * @return \Elastica\Filter\AbstractFilter
     */
    public function __call($method, $args)
    {
        if (in_array($method, ['and', 'or'])) {
            return call_user_func_array([$this, $method . '_'], $args);
        }
        throw new \BadMethodCallException('Cannot build filter ' . $method);
    }

    /**
     * Converts an array into a single array of filter objects
     *
     * ### Parsing a single array:
     *
     *   {{{
     *       $filter = $builder->parse([
     *           'name' => 'mark',
     *           'age <=' => 35
     *       ]);
     *
     *       // Equivalent to:
     *       $filter = [
     *           $builder->term('name', 'mark'),
     *           $builder->lte('age', 35)
     *       ];
     *   }}}
     *
     * ### Creating "or" conditions:
     *
     * {{{
     *  $filter = $builder->parse([
     *      'or' => [
     *          'name' => 'mark',
     *          'age <=' => 35
     *      ]
     *  ]);
     *
     *  // Equivalent to:
     *  $filter = [$builder->or(
     *      $builder->term('name', 'mark'),
     *      $builder->lte('age', 35)
     *  )];
     * }}}
     *
     * ### Negating conditions:
     *
     * {{{
     *  $filter = $builder->parse([
     *      'not' => [
     *          'name' => 'mark',
     *          'age <=' => 35
     *      ]
     *  ]);
     *
     *  // Equivalent to:
     *  $filter = [$builder->not(
     *      $builder->and(
     *          $builder->term('name', 'mark'),
     *          $builder->lte('age', 35)
     *      )
     *  )];
     * }}}
     *
     * ### Checking for field existance
     * {{{
     *       $filter = $builder->parse([
     *           'name is' => null,
     *           'age is not' => null
     *       ]);
     *
     *       // Equivalent to:
     *       $filter = [
     *           $builder->missing('name'),
     *           $builder->exists('age')
     *       ];
     * }}}
     *
     * ### Checking if a value is in a list of terms
     *
     * {{{
     *       $filter = $builder->parse([
     *           'name in' => ['jose', 'mark']
     *       ]);
     *
     *       // Equivalent to:
     *       $filter = [$builder->terms('name', ['jose', 'mark'])]
     * }}}
     *
     * The list of supported operators is:
     *
     * `<`, `>`, `<=`, `>=`, `in`, `not in`, `is`, `is not`, `!=`
     *
     * @param array|\Elastica\Filter\AbstractFilter $conditions The list of conditions to parse.
     * @return array
     */
    public function parse($conditions)
    {
        if ($conditions instanceof AbstractFilter) {
            return $conditions;
        }

        $result = [];
        foreach ($conditions as $k => $c) {
            $numericKey = is_numeric($k);
            $operator = strtolower($k);

            if ($numericKey) {
                $c = $this->parse($c);
                if (is_array($c)) {
                    $c = $this->__call('and', $c);
                }
                $result[] = $c;
                continue;
            }

            if ($operator === 'and') {
                $result[] = $this->__call('and', $this->parse($c));
                continue;
            }

            if ($operator === 'or') {
                $result[] = $this->__call('or', $this->parse($c));
                continue;
            }

            if ($operator === 'not') {
                $result[] = $this->not($this->__call('and', $this->parse($c)));
                continue;
            }

            if ($c instanceof AbstractFilter) {
                $result[] = $c;
                continue;
            }

            if (!$numericKey) {
                $result[] = $this->_parseFilter($k, $c);
            }
        }

        return $result;
    }

    /**
     * Parses a field name containing an operator into a Filter object.
     *
     * @param string $field The filed name containing the operator
     * @param mixed $value The value to pass to the filter
     * @return \Elastica\Filter\AbstractFilter
     */
    protected function _parseFilter($field, $value)
    {
        $operator = '=';
        $parts = explode(' ', trim($field), 2);

        if (count($parts) > 1) {
            list($field, $operator) = $parts;
        }

        $operator = strtolower(trim($operator));

        if ($operator === '>') {
            return $this->gt($field, $value);
        }

        if ($operator === '>=') {
            return $this->gte($field, $value);
        }

        if ($operator === '<') {
            return $this->lt($field, $value);
        }

        if ($operator === '<=') {
            return $this->lte($field, $value);
        }

        if (in_array($operator, ['in', 'not in'])) {
            $value = (array)$value;
        }

        if ($operator === 'in') {
            return $this->terms($field, $value);
        }

        if ($operator === 'not in') {
            return $this->not($this->terms($field, $value));
        }

        if ($operator === 'is' && $value === null) {
            return $this->missing($field);
        }

        if ($operator === 'is not' && $value === null) {
            return $this->exists($field);
        }

        if ($operator === 'is' && $value !== null) {
            return $this->term($field, $value);
        }

        if ($operator === 'is not' && $value !== null) {
            return $this->not($this->term($field, $value));
        }

        if ($operator === '!=') {
            return $this->not($this->term($field, $value));
        }

        return $this->term($field, $value);
    }
}
