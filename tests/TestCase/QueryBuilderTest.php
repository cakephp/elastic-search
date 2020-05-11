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
namespace Cake\ElasticSearch\Test\TestCase;

use Cake\ElasticSearch\QueryBuilder;
use Cake\TestSuite\TestCase;

/**
 * Tests the QueryBuilder class
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Tests the between() filter
     *
     * @return void
     */
    public function testBetween()
    {
        $builder = new QueryBuilder();
        $result = $builder->between('price', 10, 100);
        $expected = [
            'range' => ['price' => ['gte' => 10, 'lte' => 100]],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->between('price', '2014', '2015');
        $expected = [
            'range' => ['price' => ['gte' => '2014', 'lte' => '2015']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the bool() filter
     *
     * @return void
     */
    public function testBool()
    {
        $builder = new QueryBuilder();
        $result = $builder->bool();
        $this->assertInstanceOf('Elastica\Query\BoolQuery', $result);
    }

    /**
     * Tests the exists() filter
     *
     * @return void
     */
    public function testExists()
    {
        $builder = new QueryBuilder();
        $result = $builder->exists('comments');
        $expected = [
            'exists' => ['field' => 'comments'],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoBoundingBox() filter
     *
     * @return void
     */
    public function testGeoBoundingBox()
    {
        $builder = new QueryBuilder();
        $result = $builder->geoBoundingBox('location', [40.73, -74.1], [40.01, -71.12]);
        $expected = [
            'geo_bounding_box' => [
                'location' => [
                    'top_left' => [40.73, -74.1],
                    'bottom_right' => [40.01, -71.12],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoDistance() filter
     *
     * @return void
     */
    public function testGeoDistance()
    {
        $builder = new QueryBuilder();
        $result = $builder->geoDistance('location', ['lat' => 40.73, 'lon' => -74.1], '10km');
        $expected = [
            'geo_distance' => [
                'location' => ['lat' => 40.73, 'lon' => -74.1],
                'distance' => '10km',
            ],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->geoDistance('location', 'dr5r9ydj2y73', '10km');
        $expected = [
            'geo_distance' => [
                'location' => 'dr5r9ydj2y73',
                'distance' => '10km',
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoPolygon() filter
     *
     * @return void
     */
    public function testGeoPolygon()
    {
        $builder = new QueryBuilder();
        $result = $builder->geoPolygon('location', [
            ['lat' => 40, 'lon' => -70],
            ['lat' => 30, 'lon' => -80],
            ['lat' => 20, 'lon' => -90],
        ]);
        $expected = [
            'geo_polygon' => [
                'location' => [
                    'points' => [
                        ['lat' => 40, 'lon' => -70],
                        ['lat' => 30, 'lon' => -80],
                        ['lat' => 20, 'lon' => -90],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoShape() filter
     *
     * @return void
     */
    public function testGeoShape()
    {
        $builder = new QueryBuilder();
        $result = $builder->geoShape('location', [
            ['lat' => 40, 'lon' => -70],
            ['lat' => 30, 'lon' => -80],
        ], 'linestring');
        $expected = [
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'linestring',
                        'coordinates' => [
                            ['lat' => 40, 'lon' => -70],
                            ['lat' => 30, 'lon' => -80],
                        ],
                    ],
                    'relation' => 'intersects',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoShapeIndex() filter
     *
     * @return void
     */
    public function testGeoShapeIndex()
    {
        $builder = new QueryBuilder();
        $result = $builder->geoShapeIndex('location', 'DEU', 'countries', 'shapes', 'location');
        $expected = [
            'geo_shape' => [
                'location' => [
                    'relation' => 'intersects',
                    'indexed_shape' => [
                        'id' => 'DEU',
                        'type' => 'countries',
                        'index' => 'shapes',
                        'path' => 'location',
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the gt() filter
     *
     * @return void
     */
    public function testGt()
    {
        $builder = new QueryBuilder();
        $result = $builder->gt('price', 10);
        $expected = [
            'range' => ['price' => ['gt' => 10]],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->gt('year', '2014');
        $expected = [
            'range' => ['year' => ['gt' => '2014']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the gte() filter
     *
     * @return void
     */
    public function testGte()
    {
        $builder = new QueryBuilder();
        $result = $builder->gte('price', 10);
        $expected = [
            'range' => ['price' => ['gte' => 10]],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->gte('year', '2014');
        $expected = [
            'range' => ['year' => ['gte' => '2014']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the hasChild() filter
     *
     * @return void
     */
    public function testHashChild()
    {
        $builder = new QueryBuilder();
        $result = $builder->hasChild($builder->term('user', 'john'), 'comment');
        $expected = [
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['user' => 'john']],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the hasParent() filter
     *
     * @return void
     */
    public function testHashParent()
    {
        $builder = new QueryBuilder();
        $result = $builder->hasParent($builder->term('name', 'john'), 'user');
        $expected = [
            'has_parent' => [
                'parent_type' => 'user',
                'query' => ['term' => ['name' => 'john']],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the ids() filter
     *
     * @return void
     */
    public function testIds()
    {
        $builder = new QueryBuilder();
        $result = $builder->ids([1, 2, 3]);
        $expected = [
            'ids' => [
                'values' => [1, 2, 3],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the limit() filter
     *
     * @return void
     */
    public function testLimit()
    {
        $builder = new QueryBuilder();
        $result = $builder->limit(10);
        $expected = [
            'limit' => ['value' => 10],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the matchAll() filter
     *
     * @return void
     */
    public function testMatchAll()
    {
        $builder = new QueryBuilder();
        $result = $builder->matchAll();
        $expected = [
            'match_all' => new \stdClass(),
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the lt() filter
     *
     * @return void
     */
    public function testLt()
    {
        $builder = new QueryBuilder();
        $result = $builder->lt('price', 10);
        $expected = [
            'range' => ['price' => ['lt' => 10]],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->lt('year', '2014');
        $expected = [
            'range' => ['year' => ['lt' => '2014']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the lte() filter
     *
     * @return void
     */
    public function testLte()
    {
        $builder = new QueryBuilder();
        $result = $builder->lte('price', 10);
        $expected = [
            'range' => ['price' => ['lte' => 10]],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->lte('year', '2014');
        $expected = [
            'range' => ['year' => ['lte' => '2014']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the nested() filter
     *
     * @return void
     */
    public function testNested()
    {
        $builder = new QueryBuilder();
        $result = $builder->nested('comments', $builder->term('author', 'mark'));
        $expected = [
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['author' => 'mark']]],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the nested() filter
     *
     * @return void
     */
    public function testNestedWithQuery()
    {
        $builder = new QueryBuilder();
        $result = $builder->nested(
            'comments',
            new \Elastica\Query\SimpleQueryString('great')
        );
        $expected = [
            'nested' => [
                'path' => 'comments',
                'query' => ['simple_query_string' => ['query' => 'great']]],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the not() filter
     *
     * @return void
     */
    public function testNot()
    {
        $builder = new QueryBuilder();
        $result = $builder->not($builder->term('title', 'cake'));
        $expected = [
            'bool' => [
                'must_not' => [
                    ['term' => ['title' => 'cake']],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the prefix() filter
     *
     * @return void
     */
    public function testPrefix()
    {
        $builder = new QueryBuilder();
        $result = $builder->prefix('user', 'ki');
        $expected = [
            'prefix' => [
                'user' => [
                    'value' => 'ki',
                    'boost' => 1.0,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->prefix('user', 'ki', 2.0);
        $expected = [
            'prefix' => [
                'user' => [
                    'value' => 'ki',
                    'boost' => 2.0,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the range() filter
     *
     * @return void
     */
    public function testRange()
    {
        $builder = new QueryBuilder();
        $result = $builder->range('created', [
            'gte' => '2012-01-01',
            'lte' => 'now',
            'format' => 'dd/MM/yyyy||yyyy',
        ]);
        $expected = [
            'range' => [
                'created' => [
                    'gte' => '2012-01-01',
                    'lte' => 'now',
                    'format' => 'dd/MM/yyyy||yyyy',
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the regexp() filter
     *
     * @return void
     */
    public function testRegexp()
    {
        $builder = new QueryBuilder();
        $result = $builder->regexp('name.first', 'mar[c|k]', 2.0);
        $expected = [
            'regexp' => [
                'name.first' => [
                    'value' => 'mar[c|k]',
                    'boost' => 2.0,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the script() filter
     *
     * @return void
     */
    public function testScript()
    {
        $builder = new QueryBuilder();
        $result = $builder->script("doc['foo'] > 2");
        $expected = [
            'script' => ['script' => ['source' => "doc['foo'] > 2"]],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the simpleQueryString() filter
     *
     * @return void
     */
    public function testSimpleQueryString()
    {
        $builder = new QueryBuilder();
        $result = $builder->simpleQueryString('name', 'hello world');
        $expected = [
            'simple_query_string' => ['query' => 'hello world', 'fields' => ['name']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the term() filter
     *
     * @return void
     */
    public function testTerm()
    {
        $builder = new QueryBuilder();
        $result = $builder->term('user.name', 'jose');
        $expected = [
            'term' => ['user.name' => 'jose'],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the terms() filter
     *
     * @return void
     */
    public function testTerms()
    {
        $builder = new QueryBuilder();
        $result = $builder->terms('user.name', ['mark', 'jose']);
        $expected = [
            'terms' => ['user.name' => ['mark', 'jose']],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the type() filter
     *
     * @return void
     */
    public function testType()
    {
        $builder = new QueryBuilder();
        $result = $builder->type('products');
        $expected = [
            'type' => ['value' => 'products'],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the and() method
     *
     * @return void
     */
    public function testAnd()
    {
        $builder = new QueryBuilder();
        $result = $builder->and(
            $builder->term('user', 'jose'),
            $builder->gte('age', 29),
            $builder->and($builder->term('user', 'maria'))
        );
        $expected = [
            'bool' => [
                'must' => [
                    ['term' => ['user' => 'jose']],
                    ['range' => ['age' => ['gte' => 29]]],
                    ['bool' => ['must' => [['term' => ['user' => 'maria']]]]],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the or() method
     *
     * @return void
     */
    public function testOr()
    {
        $builder = new QueryBuilder();
        $result = $builder->or(
            $builder->term('user', 'jose'),
            $builder->gte('age', 29)
        );
        $expected = [
            'bool' => [
                'should' => [
                        ['term' => ['user' => 'jose']],
                        ['range' => ['age' => ['gte' => 29]]],
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the parse() method
     *
     * @return void
     */
    public function testParseSingleArray()
    {
        $builder = new QueryBuilder();
        $filter = $builder->parse([
            'name' => 'jose',
            'age >=' => 29,
            'age <=' => 50,
            'salary >' => 50,
            'salary <' => 60,
            'interests in' => ['cakephp', 'food'],
            'interests not in' => ['boring stuff', 'c#'],
            'profile is' => null,
            'tags is not' => null,
            'address is' => 'something',
            'address is not' => 'something else',
            'last_name !=' => 'gonzalez',
        ]);
        $expected = [
            $builder->term('name', 'jose'),
            $builder->gte('age', 29),
            $builder->lte('age', 50),
            $builder->gt('salary', 50),
            $builder->lt('salary', 60),
            $builder->terms('interests', ['cakephp', 'food']),
            $builder->not($builder->terms('interests', ['boring stuff', 'c#'])),
            $builder->not($builder->exists('profile')),
            $builder->exists('tags'),
            $builder->term('address', 'something'),
            $builder->not($builder->term('address', 'something else')),
            $builder->not($builder->term('last_name', 'gonzalez')),
        ];
        $this->assertEquals($expected, $filter);
    }

    /**
     * Tests the parse() method for generating or conditions
     *
     * @return void
     */
    public function testParseOr()
    {
        $builder = new QueryBuilder();
        $filter = $builder->parse([
            'or' => [
                'name' => 'jose',
                'age >' => 29,
            ],
        ]);
        $expected = [
            $builder->or(
                $builder->term('name', 'jose'),
                $builder->gt('age', 29)
            ),
        ];
        $this->assertEquals($expected, $filter);
    }

    /**
     * Tests the parse() method for generating and conditions
     *
     * @return void
     */
    public function testParseAnd()
    {
        $builder = new QueryBuilder();
        $filter = $builder->parse([
            'and' => [
                'name' => 'jose',
                'age >' => 29,
            ],
        ]);
        $expected = [
            $builder->and(
                $builder->term('name', 'jose'),
                $builder->gt('age', 29)
            ),
        ];
        $this->assertEquals($expected, $filter);
    }

    /**
     * Tests the parse() method for generating not conditions
     *
     * @return void
     */
    public function testParseNot()
    {
        $builder = new QueryBuilder();
        $filter = $builder->parse([
            'not' => [
                'name' => 'jose',
                'age >' => 29,
            ],
        ]);
        $expected = [
            $builder->not(
                $builder->and(
                    $builder->term('name', 'jose'),
                    $builder->gt('age', 29)
                )
            ),
        ];
        $this->assertEquals($expected, $filter);
    }

    /**
     * Tests the parse() method with numerically indexed arrays
     *
     * @return void
     */
    public function testParseNumericArray()
    {
        $builder = new QueryBuilder();
        $filter = $builder->parse([
            $builder->simpleQueryString('name', 'mark'),
            ['age >' => 29],
            'not' => [
                ['name' => 'jose'],
                ['age >' => 35],
            ],
        ]);
        $expected = [
            $builder->simpleQueryString('name', 'mark'),
            $builder->and(
                $builder->gt('age', 29)
            ),
            $builder->not(
                $builder->and(
                    $builder->and($builder->term('name', 'jose')),
                    $builder->and($builder->gt('age', 35))
                )
            ),
        ];
        $this->assertEquals($expected, $filter);
    }
}
