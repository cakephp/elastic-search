<?php

namespace Cake\ElasticSearch;

use Elastica;
use Elastica\Query\AbstractQuery;

class QueryBuilder
{

    /**
     * Returns a Range query object setup to query documents having the field between
     * a `from` and a `to` value
     *
     * @param string $field The field to query by.
     * @param mixed $from The lower bound value.
     * @param mixed $to The upper bound value.
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function between($field, $from, $to)
    {
        return $this->range($field, [
            'gte' => $from,
            'lte' => $to
        ]);
    }

    /**
     * Returns a bool query that can be chained with the `addMust()`, `addShould()`,
     * `addFilter` and `addMustNot()` methods.
     *
     * @return \Elastica\Query\BoolQuery
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
     */
    public function bool()
    {
        return new Elastica\Query\BoolQuery();
    }

    /**
     * Returns an Exists query object setup to query documents having a property present
     * or not set to null.
     *
     * @param string $field The field to check for existance.
     * @return \Elastica\Filter\Exists
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
     */
    public function exists($field)
    {
        return new Elastica\Query\Exists($field);
    }

    /**
     * Returns a GeoBoundingBox query object setup to query documents having a property
     * bound by two coordinates.
     *
     * ### Example:
     *
     * {{{
     *    $query = $builder->geoBoundingBox('location', [40.73, -74.1], [40.01, -71.12]);
     *
     *    $query = $builder->geoBoundingBox(
     *        'location',
     *        ['lat => 40.73, 'lon' => -74.1],
     *        ['lat => 40.01, 'lon' => -71.12]
     *    );
     *
     *    $query = $builder->geoBoundingBox('location', 'dr5r9ydj2y73', 'drj7teegpus6');
     * }}}
     *
     * @param string $field The field to compare.
     * @param array|string $topLeft The top left coordinate.
     * @param array|string $bottomRight The bottom right coordinate.
     * @return \Elastica\Query\GeoBoundingBox
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-bounding-box-query.html
     */
    public function geoBoundingBox($field, $topLeft, $bottomRight)
    {
        return new Elastica\Query\GeoBoundingBox($field, [$topLeft, $bottomRight]);
    }

    /**
     * Returns an GeoDistance query object setup to query documents having a property
     * in the radius distance of a coordinate.
     *
     * ### Example:
     *
     * {{{
     *    $query = $builder->geoDistance('location', ['lat' => 40.73, 'lon' => -74.1], '10km');
     *
     *    $query = $builder->geoBoundingBox('location', 'dr5r9ydj2y73', '5km');
     * }}}
     *
     * @param string $field The field to compare.
     * @param array|string $location The coordinate from which to compare.
     * @param string $distance The distance radius.
     * @return \Elastica\Query\GeoDistance
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-distance-query.html
     */
    public function geoDistance($field, $location, $distance)
    {
        return new Elastica\Query\GeoDistance($field, $location, $distance);
    }

    /**
     * Returns an GeoPolygon query object setup to query documents having a property
     * enclosed in the polygon induced by the passed geo points.
     *
     * ### Example:
     *
     * {{{
     *    $query= $builder->geoPolygon('location', [
     *        ['lat' => 40, 'lon' => -70],
     *        ['lat' => 30, 'lon' => -80],
     *        ['lat' => 20, 'lon' => -90],
     *    ]);
     *
     *    $query = $builder->geoPolygon('location', [
     *        'drn5x1g8cu2y',
     *        ['lat' => 30, 'lon' => -80],
     *        '20, -90',
     *    ]);
     * }}}
     *
     * @param string $field The field to compare.
     * @param array $geoPoints List of geo points that form the polygon
     * @return \Elastica\Query\GeoPolygon
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-polygon-query.html
     */
    public function geoPolygon($field, array $geoPoints)
    {
        return new Elastica\Query\GeoPolygon($field, $geoPoints);
    }

    /**
     * Returns an GeoShapeProvided query object setup to query documents having a property
     * enclosed in the specified geometrical shape type.
     *
     * ### Example:
     *
     * {{{
     *    $query = $builder->geoShape('location', [[13.0, 53.0], [14.0, 52.0]], 'envelope');
     *
     *    $query = $builder->geoShape('location', [
     *        [[-77.03653, 38.897676], [-77.009051, 38.889939]],
     *        'linestring'
     *    ]);
     * }}}
     *
     * You can read about the supported shapes and how they are created here:
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-shape.html
     *
     * @param string $field The field to compare.
     * @param array $geoPoints List of geo points that form the shape.
     * @param string $type The shape type to use (envelope, linestring, polygon, multipolygon...)
     * @return \Elastica\Query\GeoShapeProvided
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-query.html
     */
    public function geoShape($field, array $geoPoints, $type = Elastica\Query\GeoShapeProvided::TYPE_ENVELOPE)
    {
        return new Elastica\Query\GeoShapeProvided($field, $geoPoints, $type);
    }

    /**
     * Returns an GeoShapePreIndex query object setup to query documents having a property
     * enclosed in the specified geometrical shape type.
     *
     * ### Example:
     *
     * {{{
     *    $query = $builder->geoShapeIndex('location', 'DEU', 'countries', 'shapes', 'location');
     * }}}
     *
     * @param string $field The field to compare.
     * @param string $id The ID of the document containing the pre-indexed shape.
     * @param string $type Index type where the pre-indexed shape is.
     * @param string $index Name of the index where the pre-indexed shape is.
     * @param string $path The field specified as path containing the pre-indexed shape.
     * @return \Elastica\Query\GeoShapePreIndexed
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-query.html
     */
    public function geoShapeIndex($field, $id, $type, $index = 'shapes', $path = 'shape')
    {
        return new Elastica\Query\GeoShapePreIndexed($field, $id, $type, $index, $path);
    }

    /**
     * Returns a Range query object setup to query documents having the field
     * greater than the provided value.
     *
     * @param string $field The field to query by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function gt($field, $value)
    {
        return $this->range($field, ['gt' => $value]);
    }

    /**
     * Returns a Range query object setup to query documents having the field
     * greater than or equal the provided value.
     *
     * @param string $field The field to query by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function gte($field, $value)
    {
        return $this->range($field, ['gte' => $value]);
    }

    /**
     * Accepts a query and the child type to run against, and results in parent
     * documents that have child docs matching the query.
     *
     * @param string|\Elastica\Query|\Elastica\Query\AbstractQuery $query The query.
     * @param string $type The child type to query against.
     * @return \Elastica\Query\HasChild
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-child-query.html
     */
    public function hasChild($query, $type)
    {
        return new Elastica\Query\HasChild($query, $type);
    }

    /**
     * Query by child documents having parent documents matching the query
     *
     * @param string|\Elastica\Query|\Elastica\Query\AbstractQuery $query The query.
     * @param string $type The parent type to query against.
     * @return \Elastica\Filter\HasParent
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-parent-query.html
     */
    public function hasParent($query, $type)
    {
        return new Elastica\Query\HasParent($query, $type);
    }

    /**
     * Query documents that only have the provided ids.
     *
     * @param array $ids The list of ids to query by.
     * @return \Elastica\Query\Ids
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-ids-query.html
     */
    public function ids(array $ids = [])
    {
        return new Elastica\Query\Ids($ids);
    }

    /**
     * Limits the number of documents (per shard) to execute on.
     *
     * @param int $limit The maximum number of documents to query.
     * @return \Elastica\Query\Limit
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-limit-query.html
     */
    public function limit($limit)
    {
        return new Elastica\Query\Limit((int)$limit);
    }

    /**
     * A query that returns all documents.
     *
     * @return \Elastica\Query\MatchAll
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-all-query.html
     */
    public function matchAll()
    {
        return new Elastica\Query\MatchAll();
    }

    /**
     * Returns a Range query object setup to query documents having the field
     * smaller than the provided value.
     *
     * @param string $field The field to query by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function lt($field, $value)
    {
        return $this->range($field, ['lt' => $value]);
    }

    /**
     * Returns a Range query object setup to query documents having the field
     * smaller or equals than the provided value.
     *
     * @param string $field The field to query by.
     * @param mixed $value The value to compare with.
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function lte($field, $value)
    {
        return $this->range($field, ['lte' => $value]);
    }

    /**
     * Returns a Nested query object setup to query sub documents by a path.
     *
     * ### Example:
     *
     * {{{
     *    $builder->nested('comments', $builder->term('author', 'mark'));
     * }}}
     *
     *
     * @param string $path A dot separated string denoting the path to the property to query.
     * @param \Elastica\Query\AbstractQuery $query The query conditions.
     * @return \Elastica\Query\Nested
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-query.html
     */
    public function nested($path, $query)
    {
        $nested = new Elastica\Query\Nested();
        $nested->setPath($path);

        $nested->setQuery($query);

        return $nested;
    }

    /**
     * Returns a BoolQuery query with must_not field that is typically ussed to negate another query expression
     *
     * @param \Elastica\Query\AbstractQuery|array $query The query to negate
     * @return \Elastica\Query\BoolQuery
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html
     */
    public function not($query)
    {
        $boolQuery = new Elastica\Query\BoolQuery();
        $boolQuery->addMustNot($query);

        return $boolQuery;
    }

    /**
     * Returns a Prefix query to query documents that have fields containing terms with
     * a specified prefix
     *
     * @param string $field The field to query by.
     * @param string $prefix The prefix to check for.
     * @param float $boost The optional boost
     * @return Elastica\Query\Prefix
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-prefix-query.html
     */
    public function prefix($field, $prefix, $boost = 1.0)
    {
        $prefixQuery = new Elastica\Query\Prefix;
        $prefixQuery->setPrefix($field, $prefix, $boost);

        return $prefixQuery;
    }

    /**
     * Returns a Range query object setup to query documents having the field
     * greater than the provided values.
     *
     * The $args array accepts the following keys:
     *
     * - gte: greater than or equal
     * - gt: greater than
     * - lte: less than or equal
     * - lt: less than
     *
     * @param string $field The field to query by.
     * @param array $args An array describing the search range
     * @return \Elastica\Query\Range
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function range($field, array $args)
    {
        return new Elastica\Query\Range($field, $args);
    }

    /**
     * Returns a Regexp query to query documents based on a regular expression.
     *
     * ### Example:
     *
     * {{{
     *  $builder->regexp('name.first', 'ma.*');
     * }}}
     *
     * @param string $field The field to query by.
     * @param string $regexp The regular expression.
     * @param float $boost Boost
     * @return \Elastica\Query\Regexp
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-regexp-query.html
     */
    public function regexp($field, $regexp, $boost = 1.0)
    {
        return new Elastica\Query\Regexp($field, $regexp, $boost);
    }

    /**
     * Returns a Script query object that allows to query based on the return value of a script.
     *
     * ### Example:
     *
     * {{{
     *  $builder->script("doc['price'].value > 1");
     * }}}
     *
     * @param array|string|\Elastica\Script\AbstractScript $script The script.
     * @return \Elastica\Query\Script
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-script-query.html
     */
    public function script($script)
    {
        return new Elastica\Query\Script($script);
    }

    /**
     * Returns a SimpleQueryString object that allows to query based on a search string.
     *
     * ### Example:
     *
     * {{{
     *  $builder->simpleQueryString(['body'], '"fried eggs" +(eggplant | potato) -frittata');
     * }}}
     *
     * @param array|string $fields The fields to search within
     * @param string $string The pattern to find in the fields
     * @return \Elastica\Query\SimpleQueryString
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html
     */
    public function simpleQueryString($fields, $string)
    {
        return new Elastica\Query\SimpleQueryString($string, (array)$fields);
    }

    /**
     * Returns a Match query object that query documents that have fields containing a match.
     *
     * ### Example:
     *
     * {{{
     *  $builder->match('user.name', 'jose');
     * }}}
     *
     * @param string $field The field to query by.
     * @param string $value The match to find in field.
     * @return \Elastica\Query\Match
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html
     */
    public function match($field, $value)
    {
        return new Elastica\Query\Match($field, $value);
    }

    /**
     * Returns a Term query object that query documents that have fields containing a term.
     *
     * ### Example:
     *
     * {{{
     *  $builder->term('user.name', 'jose');
     * }}}
     *
     * @param string $field The field to query by.
     * @param string $value The term to find in field.
     * @return \Elastica\Query\Term
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
     */
    public function term($field, $value)
    {
        return new Elastica\Query\Term([$field => $value]);
    }

    /**
     * Returns a Terms query object that query documents that have fields containing some terms.
     *
     * ### Example:
     *
     * {{{
     *  $builder->terms('user.name', ['jose', 'mark']);
     * }}}
     *
     * @param string $field The field to query by.
     * @param array $values The list of terms to find in field.
     * @return \Elastica\Query\Terms
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
     */
    public function terms($field, $values)
    {
        return new Elastica\Query\Terms($field, $values);
    }

    /**
     * Returns a Type query object that query documents matching the provided document/mapping type.
     *
     * ### Example:
     *
     * {{{
     *  $builder->type('products');
     * }}}
     *
     * @param string $type The type name
     * @return \Elastica\Query\Type
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-type-query.html
     */
    public function type($type)
    {
        return new Elastica\Query\Type($type);
    }

    /**
     * Combines all the passed arguments in a single bool query
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
     * @return \Elastica\Query\BoolQuery
     */
    // @codingStandardsIgnoreStart
    public function and_()
    {
        // @codingStandardsIgnoreEnd
        $queries = func_get_args();
        $bool = $this->bool();

        foreach ($queries as $query) {
            $bool->addMust($query);
        }

        return $bool;
    }

    /**
     * Combines all the passed arguments in a single BoolQuery query using should clause.
     *
     * ### Example:
     *
     * {{{
     *  $bool = $builder->or(
     *     $builder->not($builder->exists('tags')),
     *     $builder->exists('comments')
     *  );
     * }}}
     *
     * @return \Elastica\Query\BoolQuery
     */
    // @codingStandardsIgnoreStart
    public function or_()
    {
        // @codingStandardsIgnoreEnd
        $queries = func_get_args();
        $bool = $this->bool();

        foreach ($queries as $query) {
            $bool->addShould($query);
        }

        return $bool;
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
        throw new \BadMethodCallException('Cannot build query ' . $method);
    }

    /**
     * Converts an array into a single array of query objects
     *
     * ### Parsing a single array:
     *
     *   {{{
     *       $query = $builder->parse([
     *           'name' => 'mark',
     *           'age <=' => 35
     *       ]);
     *
     *       // Equivalent to:
     *       $query = [
     *           $builder->term('name', 'mark'),
     *           $builder->lte('age', 35)
     *       ];
     *   }}}
     *
     * ### Creating "or" conditions:
     *
     * {{{
     *  $query = $builder->parse([
     *      'or' => [
     *          'name' => 'mark',
     *          'age <=' => 35
     *      ]
     *  ]);
     *
     *  // Equivalent to:
     *  $query = [$builder->or(
     *      $builder->term('name', 'mark'),
     *      $builder->lte('age', 35)
     *  )];
     * }}}
     *
     * ### Negating conditions:
     *
     * {{{
     *  $query = $builder->parse([
     *      'not' => [
     *          'name' => 'mark',
     *          'age <=' => 35
     *      ]
     *  ]);
     *
     *  // Equivalent to:
     *  $query = [$builder->not(
     *      $builder->and(
     *          $builder->term('name', 'mark'),
     *          $builder->lte('age', 35)
     *      )
     *  )];
     * }}}
     *
     * ### Checking for field existance
     * {{{
     *       $query = $builder->parse([
     *           'name is' => null,
     *           'age is not' => null
     *       ]);
     *
     *       // Equivalent to:
     *       $query = [
     *           $builder->not($builder->exists('name')),
     *           $builder->exists('age')
     *       ];
     * }}}
     *
     * ### Checking if a value is in a list of terms
     *
     * {{{
     *       $query = $builder->parse([
     *           'name in' => ['jose', 'mark']
     *       ]);
     *
     *       // Equivalent to:
     *       $query = [$builder->terms('name', ['jose', 'mark'])]
     * }}}
     *
     * The list of supported operators is:
     *
     * `<`, `>`, `<=`, `>=`, `in`, `not in`, `is`, `is not`, `!=`
     *
     * @param array|\Elastica\Query\AbstractQuery $conditions The list of conditions to parse.
     * @return array
     */
    public function parse($conditions)
    {
        if ($conditions instanceof AbstractQuery) {
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

            if ($c instanceof AbstractQuery) {
                $result[] = $c;
                continue;
            }

            if (!$numericKey) {
                $result[] = $this->_parseQuery($k, $c);
            }
        }

        return $result;
    }

    /**
     * Parses a field name containing an operator into a Filter object.
     *
     * @param string $field The filed name containing the operator
     * @param mixed $value The value to pass to the query
     * @return \Elastica\Filter\AbstractFilter
     */
    protected function _parseQuery($field, $value)
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
            return $this->not($this->exists($field));
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
