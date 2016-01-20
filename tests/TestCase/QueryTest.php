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

use Cake\ElasticSearch\FilterBuilder;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;

/**
 * Tests the Query class
 *
 */
class QueryTest extends TestCase
{

    /**
     * Tests query constructor
     *
     * @return void
     */
    public function testConstruct()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($type, $query->repository());
    }

    /**
     * Test that chained finders will work
     *
     * @return void
     */
    public function testChainedFinders()
    {
        $type = new Type();
        $query = new Query($type);
        $query->find()->find();
    }

    /**
     * Tests that executing a query means executing a search against the associated
     * Type and decorates the internal ResultSet
     *
     * @return void
     */
    public function testAll()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'foo',
            'connection' => $connection
        ]);

        $index = $this->getMockBuilder('Elastica\Index')
            ->disableOriginalConstructor()
            ->getMock();

        $internalType = $this->getMockBuilder('Elastica\Type')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('getIndex')
            ->will($this->returnValue($index));

        $index->expects($this->once())
            ->method('getType')
            ->will($this->returnValue($internalType));

        $result = $this->getMockBuilder('Elastica\ResultSet')
            ->disableOriginalConstructor()
            ->getMock();

        $internalQuery = $this->getMockBuilder('Elastica\Query')
            ->disableOriginalConstructor()
            ->getMock();

        $internalType->expects($this->once())
            ->method('search')
            ->will($this->returnCallback(function ($query) use ($result) {
                $this->assertEquals(new \Elastica\Query, $query);
                return $result;
            }));

        $query = new Query($type);
        $resultSet = $query->all();
        $this->assertInstanceOf('Cake\ElasticSearch\ResultSet', $resultSet);
        $this->assertSame($result, $resultSet->getInnerIterator());
    }

    /**
     * Tests that calling select() sets the field to select from _source
     *
     * @return void
     */
    public function testSelect()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->select(['a', 'b']));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertEquals(['a', 'b'], $elasticQuery['_source']);

        $query->select(['c', 'd']);
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertEquals(['a', 'b', 'c', 'd'], $elasticQuery['_source']);

        $query->select(['e', 'f'], true);
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertEquals(['e', 'f'], $elasticQuery['_source']);
    }

    /**
     * Tests that calling limit() sets the size option for the elastic query
     *
     * @return void
     */
    public function testLimit()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->limit(10));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(10, $elasticQuery['size']);

        $this->assertSame($query, $query->limit(20));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(20, $elasticQuery['size']);
    }

    /**
     * Tests that calling offset() sets the from option for the elastic query
     *
     * @return void
     */
    public function testOffset()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->offset(10));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(10, $elasticQuery['from']);

        $this->assertSame($query, $query->offset(20));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(20, $elasticQuery['from']);
    }

    /**
     * Tests that calling page() sets the from option for the elastic query and size (optional)
     *
     * @return void
     */
    public function testPage()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->page(10));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(225, $elasticQuery['from']);
        $this->assertSame(25, $elasticQuery['size']);

        $this->assertSame($query, $query->page(20, 50));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(950, $elasticQuery['from']);
        $this->assertSame(50, $elasticQuery['size']);

        $query->limit(15);
        $this->assertSame($query, $query->page(20));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(285, $elasticQuery['from']);
        $this->assertSame(15, $elasticQuery['size']);
    }

    /**
     * Tests that calling clause() gets the part of the query
     *
     * @return void
     */
    public function testClause()
    {
        $type = new Type();
        $query = new Query($type);

        $query->page(10);
        $this->assertSame(25, $query->clause('limit'));
        $this->assertSame(225, $query->clause('offset'));

        $query->limit(12);
        $this->assertSame(12, $query->clause('limit'));

        $query->offset(100);
        $this->assertSame(100, $query->clause('offset'));

        $query->order('price');
        $this->assertSame([ 0 => [
            'price' => [
                'order' => 'desc'
            ]
        ]], $query->clause('order'));
    }

    /**
     * Tests that calling applyOptions() sets parts of the query
     *
     * @return void
     */
    public function testApplyOptions()
    {
        $type = new Type();
        $query = new Query($type);

        $query->applyOptions([
            'fields' => ['id', 'name'],
            'conditions' => [
                'created >=' => '2013-01-01'
            ],
            'limit' => 10
        ]);

        $result = [
            '_source' => ['id', 'name'],
            'size' => 10,
            'query' => [
                'filtered' => [
                    'filter' => [
                        'bool' => [
                            'must' => [[
                                'range' => [
                                    'created' => [
                                        'gte' => '2013-01-01'
                                    ]
                                ]
                            ]]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertSame($result, $query->compileQuery()->toArray());
    }

    /**
     * Tests that calling order() will populate the sort part of the elastic
     * query.
     *
     * @return void
     */
    public function testOrder()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->order('price'));

        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [['price' => ['order' => 'desc']]];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->order(['created' => 'asc']);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']]
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->order(['modified' => 'desc', 'score' => 'asc']);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']],
            ['modified' => ['order' => 'desc']],
            ['score' => ['order' => 'asc']],
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->order(['clicks' => ['mode' => 'avg', 'order' => 'asc']]);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']],
            ['modified' => ['order' => 'desc']],
            ['score' => ['order' => 'asc']],
            ['clicks' => ['mode' => 'avg', 'order' => 'asc']]
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->order(['created' => 'asc'], true);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['created' => ['order' => 'asc']]
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);
    }

    /**
     * Tests the where() method
     *
     * @return void
     */
    public function testWhere()
    {
        $type = new Type();
        $query = new Query($type);
        $query->where([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java']
            ]
        ]);

        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['query']['filtered']['filter']['bool']['must'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $filter[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $filter[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $filter[2]['or'][0]);

        $expected = [
            'not' => [
                'filter' => [
                    'terms' => ['interests' => ['c#', 'java']]
                ]
            ]
        ];
        $this->assertEquals($expected, $filter[2]['or'][1]);

        $query->where(function (FilterBuilder $builder) {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff')
            );
        });

        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['query']['filtered']['filter']['bool']['must'];
        $filter = $filter[3]['bool']['must'];
        $expected = [
            ['term' => ['another.thing' => 'value']],
            ['exists' => ['field' => 'stuff']],
        ];
        $this->assertEquals($expected, $filter);

        $query->where(['name.first' => 'jose'], true);
        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['query']['filtered']['filter']['bool']['must'];
        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals([$expected], $filter);
    }

    /**
     * Tests the postFilter() method
     *
     * @return void
     */
    public function testPostFilter()
    {
        $type = new Type();
        $query = new Query($type);
        $query->postFilter([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java']
            ]
        ]);

        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['post_filter']['bool']['must'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $filter[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $filter[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $filter[2]['or'][0]);

        $expected = [
            'not' => [
                'filter' => [
                    'terms' => ['interests' => ['c#', 'java']]
                ]
            ]
        ];
        $this->assertEquals($expected, $filter[2]['or'][1]);

        $query->postFilter(function (FilterBuilder $builder) {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff')
            );
        });

        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['post_filter']['bool']['must'];
        $filter = $filter[3]['bool']['must'];
        $expected = [
            ['term' => ['another.thing' => 'value']],
            ['exists' => ['field' => 'stuff']],
        ];
        $this->assertEquals($expected, $filter);

        $query->postFilter(['name.first' => 'jose'], true);
        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['post_filter']['bool']['must'];
        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals([$expected], $filter);
    }

    /**
     * Tests that it is possible to pass a 0 as limit
     *
     * @return void
     */
    public function testLimitZero()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->limit(0));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(0, $elasticQuery['size']);
    }

    /**
     * Tests that it is possible to pass a 0 as offset
     *
     * @return void
     */
    public function testOffsetZero()
    {
        $type = new Type();
        $query = new Query($type);
        $this->assertSame($query, $query->offset(0));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(0, $elasticQuery['from']);
    }

    /**
     * Test setting highlights.
     *
     * @return void
     */
    public function testHighlight()
    {
        $type = new Type();
        $query = new Query($type);
        $query->highlight([
            'pre_tags' => [''],
            'post_tags' => [''],
            'fields' => [
                'contents' => [
                    'fragment_size' => 100,
                    'number_of_fragments' => 3
                ],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();
        $this->assertArrayHasKey('pre_tags', $compiled['highlight']);
        $this->assertArrayHasKey('post_tags', $compiled['highlight']);
        $this->assertArrayHasKey('fields', $compiled['highlight']);
        $this->assertEquals(100, $compiled['highlight']['fields']['contents']['fragment_size']);
    }
}
