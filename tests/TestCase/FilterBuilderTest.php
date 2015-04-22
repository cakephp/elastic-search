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
namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\FilterBuilder;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;
use Elastica\Filter;

/**
 * Tests the FilterBuilder class
 *
 */
class FilterBuilderTest extends TestCase
{

    /**
     * Tests the between() filter
     *
     * @return void
     */
    public function testBetween()
    {
        $builder = new FilterBuilder;
        $result = $builder->between('price', 10, 100);
        $expected = [
            'range' => ['price' => ['gte' => 10, 'lte' => 100]]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->between('price', '2014', '2015');
        $expected = [
            'range' => ['price' => ['gte' => '2014', 'lte' => '2015']]
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
        $builder = new FilterBuilder;
        $result = $builder->bool();
        $this->assertInstanceOf('Elastica\Filter\Bool', $result);
    }

    /**
     * Tests the exists() filter
     *
     * @return void
     */
    public function testExists()
    {
        $builder = new FilterBuilder;
        $result = $builder->exists('comments');
        $expected = [
            'exists' => ['field' => 'comments']
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
        $builder = new FilterBuilder;
        $result = $builder->geoBoundingBox('location', [40.73, -74.1], [40.01, -71.12]);
        $expected = [
            'geo_bounding_box' => [
                'location' => [
                    'top_left' => [40.73, -74.1],
                    'bottom_right' => [40.01, -71.12]
                ]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->geoDistance('location', ['lat' => 40.73, 'lon' => -74.1], '10km');
        $expected = [
            'geo_distance' => [
                'location' => ['lat' => 40.73, 'lon' => -74.1],
                'distance' => '10km'
            ]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->geoDistance('location', 'dr5r9ydj2y73', '10km');
        $expected = [
            'geo_distance' => [
                'location' => 'dr5r9ydj2y73',
                'distance' => '10km'
            ]
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoDistanceRange() filter
     *
     * @return void
     */
    public function testGeoDistanceRange()
    {
        $builder = new FilterBuilder;
        $result = $builder->geoDistanceRange('location', ['lat' => 40.73, 'lon' => -74.1], '5km', '6km');
        $expected = [
            'geo_distance_range' => [
                'location' => ['lat' => 40.73, 'lon' => -74.1],
                'gte' => '5km',
                'lte' => '6km',
            ]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->geoDistanceRange('location', 'dr5r9ydj2y73', '10km', '15km');
        $expected = [
            'geo_distance_range' => [
                'location' => 'dr5r9ydj2y73',
                'gte' => '10km',
                'lte' => '15km',
            ]
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
        $builder = new FilterBuilder;
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
                        ['lat' => 20, 'lon' => -90]
                    ]
                ]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->geoShape('location', [
            ['lat' => 40, 'lon' => -70],
            ['lat' => 30, 'lon' => -80],
        ], 'linestring');
        $expected = [
            'geo_shape' => [
                'location' => [
                    'relation' => 'intersects',
                    'shape' => [
                        'type' => 'linestring',
                        'coordinates' => [
                            ['lat' => 40, 'lon' => -70],
                            ['lat' => 30, 'lon' => -80],
                        ]
                    ]
                ]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->geoShapeIndex('location', 'DEU', 'countries', 'shapes', 'location');
        $expected = [
            'geo_shape' => [
                'location' => [
                    'relation' => 'intersects',
                    'indexed_shape' => [
                        'id' => 'DEU',
                        'type' => 'countries',
                        'index' => 'shapes',
                        'path' => 'location'
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the geoHashCell() filter
     *
     * @return void
     */
    public function testGeoHashCell()
    {
        $builder = new FilterBuilder;
        $result = $builder->geoHashCell('location', ['lat' => 40.73, 'lon' => -74.1], 3);
        $expected = [
            'geohash_cell' => [
                'location' => ['lat' => 40.73, 'lon' => -74.1],
                'precision' => 3,
                'neighbors' => false,
            ]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->geoHashCell('location', 'dr5r9ydj2y73', '50m', true);
        $expected = [
            'geohash_cell' => [
                'location' => 'dr5r9ydj2y73',
                'precision' => '50m',
                'neighbors' => true,
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->gt('price', 10);
        $expected = [
            'range' => ['price' => ['gt' => 10]]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->gt('year', '2014');
        $expected = [
            'range' => ['year' => ['gt' => '2014']]
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
        $builder = new FilterBuilder;
        $result = $builder->gte('price', 10);
        $expected = [
            'range' => ['price' => ['gte' => 10]]
        ];
        $this->assertEquals($expected, $result->toArray());

        $result = $builder->gte('year', '2014');
        $expected = [
            'range' => ['year' => ['gte' => '2014']]
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
        $builder = new FilterBuilder;
        $result = $builder->hasChild($builder->term('user', 'john'), 'comment');
        $expected = [
            'has_child' => [
                'type' => 'comment',
                'filter' => ['term' => ['user' => 'john']]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->hasParent($builder->term('name', 'john'), 'user');
        $expected = [
            'has_parent' => [
                'type' => 'user',
                'filter' => ['term' => ['name' => 'john']]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->ids([1, 2, 3], 'user');
        $expected = [
            'ids' => [
                'type' => 'user',
                'values' => [1, 2, 3]
            ]
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Tests the indices() filter
     *
     * @return void
     */
    public function testIndices()
    {
        $builder = new FilterBuilder;
        $result = $builder->indices(
            ['a', 'b'],
            $builder->term('user', 'mark'),
            $builder->term('tag', 'wow')
        );
        $expected = [
            'indices' => [
                'indices' => ['a', 'b'],
                'filter' => ['term' => ['user' => 'mark']],
                'no_match_filter' => ['term' => ['tag' => 'wow']]
            ]
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
        $builder = new FilterBuilder;
        $result = $builder->limit(10);
        $expected = [
            'limit' => ['value' => 10]
        ];
        $this->assertEquals($expected, $result->toArray());
    }
}
