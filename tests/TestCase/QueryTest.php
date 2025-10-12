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
namespace Cake\ElasticSearch\Test\TestCase;

use AssertionError;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\QueryBuilder;
use Cake\ElasticSearch\TestSuite\TestCase;
use Closure;
use Elastica\Aggregation\Avg;
use Elastica\Aggregation\Max as MaxAggregation;
use Elastica\Aggregation\Min as MinAggregation;
use Elastica\Collapse;
use Elastica\Query as ElasticaQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use InvalidArgumentException;
use Traversable;

/**
 * Tests the Query class
 */
class QueryTest extends TestCase
{
    use IndexLocatorAwareTrait;

    /**
     * Tests query constructor
     */
    public function testConstruct(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($index, $query->getRepository());
    }

    /**
     * Test that chained finders will work
     */
    public function testChainedFinders(): void
    {
        $index = new Index();
        $query = new Query($index);

        $finder = $query->find('all')->find('all');
        $this->assertInstanceOf(Query::class, $finder);
    }

    /**
     * Test that query overwrite any query
     */
    public function testSetFullQuery(): void
    {
        $index = new Index();
        $query = new Query($index);

        $query
            ->where(['name' => 'test'])
            ->setFullQuery(new Term(['name' => 'cake']));

        $expected = ['query' => [
            'term' => [
                'name' => 'cake',
            ],
        ]];

        $this->assertSame($expected, $query->compileQuery()->toArray());
    }

    /**
     * Tests that calling select() sets the field to select from _source
     */
    public function testSelect(): void
    {
        $index = new Index();
        $query = new Query($index);
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
     */
    public function testLimit(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->limit(10));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(10, $elasticQuery['size']);

        $this->assertSame($query, $query->limit(20));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(20, $elasticQuery['size']);
    }

    /**
     * Tests that calling offset() sets the from option for the elastic query
     */
    public function testOffset(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->offset(10));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(10, $elasticQuery['from']);

        $this->assertSame($query, $query->offset(20));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(20, $elasticQuery['from']);
    }

    /**
     * Tests that calling page() sets the from option for the elastic query and size (optional)
     */
    public function testPage(): void
    {
        $index = new Index();
        $query = new Query($index);
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
     */
    public function testClause(): void
    {
        $index = new Index();
        $query = new Query($index);

        $query->page(10);
        $this->assertSame(25, $query->clause('limit'));
        $this->assertSame(225, $query->clause('offset'));

        $query->limit(12);
        $this->assertSame(12, $query->clause('limit'));

        $query->offset(100);
        $this->assertSame(100, $query->clause('offset'));

        $query->orderBy('price');
        $this->assertSame([ 0 => [
            'price' => [
                'order' => 'desc',
            ],
        ]], $query->clause('order'));
    }

    /**
     * Tests that calling applyOptions() sets parts of the query
     */
    public function testApplyOptions(): void
    {
        $index = new Index();
        $query = new Query($index);

        $query->applyOptions([
            'fields' => ['id', 'name'],
            'conditions' => [
                'created >=' => '2013-01-01',
            ],
            'limit' => 10,
        ]);

        $result = [
            '_source' => ['id', 'name'],
            'size' => 10,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['bool' => [
                            'must' => [[
                                'range' => [
                                    'created' => [
                                        'gte' => '2013-01-01',
                                    ],
                                ],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];

        $this->assertSame($result, $query->compileQuery()->toArray());
    }

    /**
     * Tests that calling order() will populate the sort part of the elastic
     * query.
     */
    public function testOrder(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->orderBy('price'));

        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [['price' => ['order' => 'desc']]];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->orderBy(['created' => 'asc']);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']],
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->orderBy(['modified' => 'desc', 'score' => 'asc']);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']],
            ['modified' => ['order' => 'desc']],
            ['score' => ['order' => 'asc']],
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->orderBy(['clicks' => ['mode' => 'avg', 'order' => 'asc']]);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['price' => ['order' => 'desc']],
            ['created' => ['order' => 'asc']],
            ['modified' => ['order' => 'desc']],
            ['score' => ['order' => 'asc']],
            ['clicks' => ['mode' => 'avg', 'order' => 'asc']],
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);

        $query->orderBy(['created' => 'asc'], true);
        $elasticQuery = $query->compileQuery()->toArray();
        $expected = [
            ['created' => ['order' => 'asc']],
        ];
        $this->assertEquals($expected, $elasticQuery['sort']);
    }

    /**
     * Tests the where() method
     */
    public function testWhere(): void
    {
        $index = new Index();
        $query = new Query($index);
        $query->where([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java'],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();

        $filter = $compiled['query']['bool']['filter'][0]['bool']['must'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $filter[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $filter[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $filter[2]['bool']['should'][0]);

        $expected = [
            'bool' => [
                'must_not' => [
                    ['terms' => ['interests' => ['c#', 'java']]],
                ],
            ],
        ];
        $this->assertEquals($expected, $filter[2]['bool']['should'][1]);

        $query->where(function (QueryBuilder $builder): BoolQuery {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff'),
            );
        });

        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['query']['bool']['filter'][0]['bool']['must'];
        $filter = $filter[3]['bool']['must'];

        $expected = [
            ['term' => ['another.thing' => 'value']],
            ['exists' => ['field' => 'stuff']],
        ];
        $this->assertEquals($expected, $filter);

        $query->where(['name.first' => 'jose'], [], true);
        $compiled = $query->compileQuery()->toArray();
        $filter = $compiled['query']['bool']['filter'][0]['bool']['must'];
        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals([$expected], $filter);
    }

    /**
     * Tests the query() method
     */
    public function testQueryMust(): void
    {
        $index = new Index();
        $query = new Query($index);
        $query->queryMust([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java'],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();

        $must = $compiled['query']['bool']['must'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $must[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $must[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $must[2]['bool']['should'][0]);

        $expected = [
            'bool' => [
                'must_not' => [
                    ['terms' => ['interests' => ['c#', 'java']]],
                ],
            ],
        ];
        $this->assertEquals($expected, $must[2]['bool']['should'][1]);

        $query->queryMust(function (QueryBuilder $builder): BoolQuery {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff'),
            );
        });

        $compiled = $query->compileQuery()->toArray();
        $must = $compiled['query']['bool']['must'];
        $must = $must[3]['bool']['must'];

        $expected = [
            ['term' => ['another.thing' => 'value']],
            ['exists' => ['field' => 'stuff']],
        ];
        $this->assertEquals($expected, $must);

        $query->queryMust(['name.first' => 'jose'], true);
        $compiled = $query->compileQuery()->toArray();
        $must = $compiled['query']['bool']['must'];
        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals([$expected], $must);
    }

    public function testQueryShould(): void
    {
        $index = new Index();
        $query = new Query($index);
        $query->queryShould([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java'],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();

        $should = $compiled['query']['bool']['should'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $should[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $should[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $should[2]['bool']['should'][0]);

        $expected = [
            'bool' => [
                'must_not' => [
                    ['terms' => ['interests' => ['c#', 'java']]],
                ],
            ],
        ];
        $this->assertEquals($expected, $should[2]['bool']['should'][1]);

        $query->queryShould(function (QueryBuilder $builder): BoolQuery {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff'),
            );
        });

        $compiled = $query->compileQuery()->toArray();
        $should = $compiled['query']['bool']['should'];
        $should = $should[3]['bool']['must'];

        $expected = [
            ['term' => ['another.thing' => 'value']],
            ['exists' => ['field' => 'stuff']],
        ];
        $this->assertEquals($expected, $should);

        $query->queryShould(['name.first' => 'jose'], true);
        $compiled = $query->compileQuery()->toArray();
        $should = $compiled['query']['bool']['should'];
        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals([$expected], $should);
    }

    /**
     * Tests the postFilter() method
     */
    public function testPostFilter(): void
    {
        $index = new Index();
        $query = new Query($index);
        $query->postFilter([
            'name.first' => 'jose',
            'age >' => 29,
            'or' => [
                'tags in' => ['cake', 'php'],
                'interests not in' => ['c#', 'java'],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();

        $filter = $compiled['post_filter']['bool']['must'];

        $expected = ['term' => ['name.first' => 'jose']];
        $this->assertEquals($expected, $filter[0]);

        $expected = ['range' => ['age' => ['gt' => 29]]];
        $this->assertEquals($expected, $filter[1]);

        $expected = ['terms' => ['tags' => ['cake', 'php']]];
        $this->assertEquals($expected, $filter[2]['bool']['should'][0]);

        $expected = [
            'bool' => [
                'must_not' => [
                        ['terms' => ['interests' => ['c#', 'java']]],
                ],
            ],
        ];
        $this->assertEquals($expected, $filter[2]['bool']['should'][1]);

        $query->postFilter(function (QueryBuilder $builder): BoolQuery {
            return $builder->and(
                $builder->term('another.thing', 'value'),
                $builder->exists('stuff'),
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
     */
    public function testLimitZero(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->limit(0));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(0, $elasticQuery['size']);
    }

    /**
     * Tests that it is possible to pass a 0 as offset
     */
    public function testOffsetZero(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->offset(0));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(0, $elasticQuery['from']);
    }

    /**
     * Test setting collapse.
     */
    public function testCollapse(): void
    {
        $index = new Index();
        $query = new Query($index);

        $query->collapse('username');

        $compiled = $query->compileQuery()->toArray();
        $this->assertSame(['field' => 'username'], $compiled['collapse']);

        $query->collapse((new Collapse())->setFieldname('email'));
        $compiled = $query->compileQuery()->toArray();
        $this->assertSame(['field' => 'email'], $compiled['collapse']);
    }

    /**
     * Test setting aggregations.
     */
    public function testAggregations(): void
    {
        $index = new Index();
        $query = new Query($index);

        $maxAggregation = (new MaxAggregation('max_id'))->setField('id');
        $maxCompiled = ['max_id' => ['max' => ['field' => 'id']]];
        $minAggregation = (new MinAggregation('min_id'))->setField('id');
        $minCompiled = ['min_id' => ['min' => ['field' => 'id']]];

        $query->aggregate((new MaxAggregation('max_id'))->setField('id'));
        $compiled = $query->compileQuery()->toArray();
        $this->assertSame($maxCompiled, $compiled['aggs']);

        $query->aggregate((new MinAggregation('min_id'))->setField('id'));
        $compiled = $query->compileQuery()->toArray();
        $this->assertSame($maxCompiled + $minCompiled, $compiled['aggs']);

        $query = new Query($index);
        $query->aggregate([$maxAggregation, $minAggregation]);
        $this->assertSame($maxCompiled + $minCompiled, $compiled['aggs']);
    }

    /**
     * Test setting highlights.
     */
    public function testHighlight(): void
    {
        $index = new Index();
        $query = new Query($index);
        $query->highlight([
            'pre_tags' => [''],
            'post_tags' => [''],
            'fields' => [
                'contents' => [
                    'fragment_size' => 100,
                    'number_of_fragments' => 3,
                ],
            ],
        ]);

        $compiled = $query->compileQuery()->toArray();
        $this->assertArrayHasKey('pre_tags', $compiled['highlight']);
        $this->assertArrayHasKey('post_tags', $compiled['highlight']);
        $this->assertArrayHasKey('fields', $compiled['highlight']);
        $this->assertSame(100, $compiled['highlight']['fields']['contents']['fragment_size']);
    }

    /**
     * Tests that it is possible to pass a min score
     */
    public function testMinScore(): void
    {
        $index = new Index();
        $query = new Query($index);
        $this->assertSame($query, $query->withMinScore(1));
        $elasticQuery = $query->compileQuery()->toArray();
        $this->assertSame(1.0, $elasticQuery['min_score']);
    }

    /**
     * Test searchOptions method
     */
    public function testSearchOptionsMethod(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test getter
        $options = $query->searchOptions();
        $this->assertIsArray($options);

        // Test setter
        $newOptions = ['timeout' => '1s', 'routing' => 'test'];
        $result = $query->searchOptions($newOptions);
        $this->assertSame($query, $result); // Test fluent interface

        $retrievedOptions = $query->searchOptions();
        $this->assertEquals($newOptions, $retrievedOptions);
    }

    /**
     * Test count method
     */
    public function testCountMethod(): void
    {
        $index = $this->getIndex();
        $query = new Query($index);

        // This will execute against the test ElasticSearch instance
        $count = $query->count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * Test cache method
     */
    public function testCacheMethod(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test enabling cache
        $result = $query->cache('test_key');
        $this->assertSame($query, $result); // Test fluent interface

        // Test disabling cache
        $result = $query->cache(false);
        $this->assertSame($query, $result);
    }

    /**
     * Test mapReduce method
     */
    public function testMapReduceMethod(): void
    {
        $index = new Index();
        $query = new Query($index);

        $mapper = function ($doc, $key, $mapReduce) {
            $mapReduce->emitIntermediate($doc['category'], $key);
        };

        $reducer = function ($bucket, $name, $reducer) {
            $reducer->emit($bucket);
        };

        // Test adding map reduce
        $result = $query->mapReduce($mapper, $reducer);
        $this->assertSame($query, $result); // Test fluent interface

        $mapReducers = $query->getMapReducers();
        $this->assertCount(1, $mapReducers);

        // Test overwrite
        $query->mapReduce($mapper, $reducer, true);
        $mapReducers = $query->getMapReducers();
        $this->assertCount(1, $mapReducers); // Should still be 1 due to overwrite
    }

    /**
     * Test formatResults method
     */
    public function testFormatResultsMethod(): void
    {
        $index = new Index();
        $query = new Query($index);

        $formatter = function ($results) {
            return $results->map(function ($row) {
                $row['formatted'] = true;

                return $row;
            });
        };

        // Test adding formatter
        $result = $query->formatResults($formatter);
        $this->assertSame($query, $result); // Test fluent interface

        $formatters = $query->getResultFormatters();
        $this->assertCount(1, $formatters);

        // Test overwrite mode
        $query->formatResults($formatter, Query::OVERWRITE);
        $formatters = $query->getResultFormatters();
        $this->assertCount(1, $formatters);
    }

    /**
     * Test firstOrFail method
     */
    public function testFirstOrFailMethod(): void
    {
        $index = $this->getIndex();

        // Test with query that should return no results
        $query = new Query($index);
        $query->where(['title' => 'NonExistentTitle' . uniqid()]);

        $this->expectException(RecordNotFoundException::class);
        $query->firstOrFail();
    }

    /**
     * Test aliasField and aliasFields methods
     */
    public function testAliasFieldMethods(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test aliasField
        $result = $query->aliasField('title');
        $this->assertEquals(['title' => 'title'], $result);

        $result = $query->aliasField('title', 'custom_alias');
        $this->assertEquals(['title' => 'title'], $result); // Elasticsearch doesn't use aliases the same way

        // Test aliasFields
        $fields = ['title', 'body', 'created'];
        $result = $query->aliasFields($fields);
        $expected = [
            'title' => 'title',
            'body' => 'body',
            'created' => 'created',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getOptions method
     */
    public function testGetOptionsMethod(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test initial empty options
        $options = $query->getOptions();
        $this->assertIsArray($options);

        // Test after setting some options
        $testOptions = ['limit' => 10, 'page' => 2];
        $query->applyOptions($testOptions);

        $options = $query->getOptions();
        $this->assertIsArray($options);
    }

    /**
     * Test toArray method
     */
    public function testToArrayMethod(): void
    {
        $index = $this->getIndex();
        $query = new Query($index);
        $query->limit(1); // Limit to avoid large result sets

        $result = $query->toArray();
        $this->assertIsArray($result);
    }

    /**
     * Test getIterator method
     */
    public function testGetIteratorMethod(): void
    {
        $index = $this->getIndex();
        $query = new Query($index);
        $query->limit(1); // Limit to avoid large result sets

        $iterator = $query->getIterator();
        $this->assertInstanceOf(Traversable::class, $iterator);

        // Test that we can iterate
        foreach ($query as $item) {
            // Just test that iteration works
            break;
        }
        $this->assertTrue(true); // If we get here, iteration worked
    }

    /**
     * Test andWhere method
     */
    public function testAndWhere(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test with array conditions
        $query->andWhere(['title' => 'test']);
        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);

        // Test with closure
        $query->andWhere(function ($builder) {
            return $builder->term('status', 'active');
        });
        $this->assertInstanceOf(Query::class, $query);
    }

    /**
     * Test compileQuery method
     */
    public function testCompileQuery(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test basic query compilation
        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);

        // Test with various query parts
        $query->select(['title', 'body'])
              ->limit(10)
              ->offset(5)
              ->where(['status' => 'published']);

        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);

        // Verify query has expected structure
        $queryArray = $compiled->toArray();
        $this->assertEquals(10, $queryArray['size']);
        $this->assertEquals(5, $queryArray['from']);
        $this->assertArrayHasKey('_source', $queryArray);
    }

    /**
     * Test setRepository method
     */
    public function testSetRepository(): void
    {
        $index1 = new Index();
        $index2 = new Index();
        $query = new Query($index1);

        $this->assertSame($index1, $query->getRepository());

        $result = $query->setRepository($index2);
        $this->assertSame($query, $result); // Test fluent interface
        $this->assertSame($index2, $query->getRepository());
    }

    /**
     * Test orderBy method (different from deprecated order method)
     */
    public function testOrderBy(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test with string field
        $result = $query->orderBy('title');
        $this->assertSame($query, $result);

        // Test with array
        $query->orderBy(['title' => 'asc', 'created' => 'desc']);
        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);

        // Test with closure
        $query->orderBy(function ($order) {
            return ['_score' => ['order' => 'desc']];
        });
        $this->assertInstanceOf(Query::class, $query);
    }

    /**
     * Test find method with custom finder
     */
    public function testFindMethod(): void
    {
        $index = $this->createMock(Index::class);
        $query = new Query($index);

        // Mock the callFinder method
        $index->expects($this->once())
              ->method('callFinder')
              ->with('published', $query, ['active'])
              ->willReturn($query);

        $result = $query->find('published', 'active');
        $this->assertSame($query, $result);
    }

    /**
     * Test all method
     */
    public function testAllMethod(): void
    {
        $index = $this->getIndex();
        $query = new Query($index);
        $query->limit(1); // Limit to avoid large result sets

        $result = $query->all();
        $this->assertInstanceOf(ResultSetInterface::class, $result);

        // Test that calling all() again returns cached results
        $result2 = $query->all();
        $this->assertSame($result, $result2);
    }

    /**
     * Test first method
     */
    public function testFirstMethod(): void
    {
        $index = $this->getIndex();
        $query = new Query($index);

        $result = $query->first();
        // Result could be null or a Document depending on data
        $this->assertTrue($result === null || is_object($result));

        // Test with forced dirty state
        $query->where(['nonexistent_field' => 'value']);
        $result = $query->first();
        $this->assertTrue($result === null || is_object($result));
    }

    /**
     * Test getMapReducers method
     */
    public function testGetMapReducers(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Initially empty
        $reducers = $query->getMapReducers();
        $this->assertIsArray($reducers);
        $this->assertEmpty($reducers);

        // Add a map reducer
        $mapper = function ($doc, $key, $emit) {
            $emit($doc['category'], 1);
        };
        $reducer = function ($category, $values, $emit) {
            $emit($category, array_sum($values));
        };

        $query->mapReduce($mapper, $reducer);
        $reducers = $query->getMapReducers();
        $this->assertCount(1, $reducers);
    }

    /**
     * Test getResultFormatters method
     */
    public function testGetResultFormatters(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Initially empty
        $formatters = $query->getResultFormatters();
        $this->assertIsArray($formatters);
        $this->assertEmpty($formatters);

        // Add a formatter
        $formatter = function ($results) {
            return $results;
        };

        $query->formatResults($formatter);
        $formatters = $query->getResultFormatters();
        $this->assertCount(1, $formatters);
        $this->assertInstanceOf(Closure::class, $formatters[0]);
    }

    /**
     * Test formatResults with overwrite mode
     */
    public function testFormatResultsOverwrite(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Add first formatter
        $formatter1 = function ($results) {
            return 'formatter1';
        };
        $query->formatResults($formatter1);

        // Add second formatter with overwrite
        $formatter2 = function ($results) {
            return 'formatter2';
        };
        $query->formatResults($formatter2, Query::OVERWRITE);

        $formatters = $query->getResultFormatters();
        $this->assertCount(1, $formatters);
    }

    /**
     * Test mapReduce with overwrite mode
     */
    public function testMapReduceOverwrite(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Add first map reducer
        $mapper1 = function ($doc, $key, $emit) {
            $emit('test1', 1);
        };
        $query->mapReduce($mapper1);

        // Add second with overwrite
        $mapper2 = function ($doc, $key, $emit) {
            $emit('test2', 1);
        };
        $query->mapReduce($mapper2, null, true);

        $reducers = $query->getMapReducers();
        $this->assertCount(1, $reducers);
    }

    /**
     * Test cache method with different configurations
     */
    public function testCacheMethodExtended(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test with closure key
        $keyGenerator = function ($q) {
            return 'dynamic_key';
        };
        $result = $query->cache($keyGenerator);
        $this->assertSame($query, $result);

        // Test disabling cache
        $query->cache(false);
        $this->assertSame($query, $result);
    }

    /**
     * Test searchOptions getter and setter
     */
    public function testSearchOptionsGetterSetter(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test getter returns empty array initially
        $options = $query->searchOptions();
        $this->assertIsArray($options);

        // Test setter
        $newOptions = ['timeout' => '10s', 'preference' => '_local'];
        $result = $query->searchOptions($newOptions);
        $this->assertSame($query, $result);

        // Test getter returns set options
        $this->assertEquals($newOptions, $query->searchOptions());
    }

    /**
     * Test edge cases in applyOptions
     */
    public function testApplyOptionsEdgeCases(): void
    {
        $index = new Index();
        $query = new Query($index);

        // Test with empty options
        $result = $query->applyOptions([]);
        $this->assertSame($query, $result);

        // Test with all valid options
        $options = [
            'fields' => ['title', 'body'],
            'conditions' => ['status' => 'active'],
            'limit' => 20,
            'offset' => 10,
            'sort' => ['created' => 'desc'],
            'order' => ['title' => 'asc'],
        ];

        $query->applyOptions($options);
        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);
    }

    /**
     * Helper method to get a real index for integration tests
     */
    private function getIndex(): Index
    {
        $connection = ConnectionManager::get('test');

        return $this->fetchIndex('Articles', [
            'className' => 'Cake\ElasticSearch\Index',
            'connection' => $connection,
        ]);
    }

    /**
     * Test cache method with false key (disable caching)
     */
    public function testCacheWithFalseKey(): void
    {
        $index = new Index();
        $query = new Query($index);

        $result = $query->cache(false);
        $this->assertSame($query, $result);
        $this->assertNull($query->getOptions()['cache'] ?? null);
    }

    /**
     * Test cache method with closure key
     */
    public function testCacheWithClosureKey(): void
    {
        $index = new Index();
        $query = new Query($index);

        $keyGenerator = function ($q) {
            return 'generated_key_' . md5(serialize($q->clause('select')));
        };

        $result = $query->cache($keyGenerator, 'test_cache');
        $this->assertSame($query, $result);
    }

    /**
     * Test aliasField method
     */
    public function testAliasField(): void
    {
        $index = new Index();
        $query = new Query($index);

        $result = $query->aliasField('title');
        $expected = ['title' => 'title'];
        $this->assertSame($expected, $result);

        $result = $query->aliasField('title', 'articles');
        $expected = ['title' => 'title'];
        $this->assertSame($expected, $result);
    }

    /**
     * Test aliasFields method
     */
    public function testAliasFields(): void
    {
        $index = new Index();
        $query = new Query($index);

        $fields = ['title', 'body', 'created'];
        $result = $query->aliasFields($fields);
        $expected = [
            'title' => 'title',
            'body' => 'body',
            'created' => 'created',
        ];
        $this->assertSame($expected, $result);
    }

    /**
     * Test firstOrFail when no results exist
     */
    public function testFirstOrFailWithNoResults(): void
    {
        $index = $this->fetchIndex('Articles');

        $query = $index->find()
            ->where(['title' => 'nonexistent_article_title_that_should_not_exist']);

        $this->expectException(RecordNotFoundException::class);
        $query->firstOrFail();
    }

    /**
     * Test mapReduce with overwrite flag
     */
    public function testMapReduceWithOverwrite(): void
    {
        $index = new Index();
        $query = new Query($index);

        $mapper1 = function ($key, $value, $mapReduce) {
            $mapReduce->emitIntermediate($value, 'first');
        };
        $reducer1 = function ($key, $values, $mapReduce) {
            $mapReduce->emit($key, count($values));
        };

        $mapper2 = function ($key, $value, $mapReduce) {
            $mapReduce->emitIntermediate($value, 'second');
        };
        $reducer2 = function ($key, $values, $mapReduce) {
            $mapReduce->emit($key, array_sum($values));
        };

        // Add first mapper/reducer
        $query->mapReduce($mapper1, $reducer1);
        $this->assertCount(1, $query->getMapReducers());

        // Add second with overwrite=false (should append)
        $query->mapReduce($mapper2, $reducer2, false);
        $this->assertCount(2, $query->getMapReducers());

        // Add third with overwrite=true (should replace all)
        $query->mapReduce($mapper1, $reducer1, true);
        $this->assertCount(1, $query->getMapReducers());
    }

    /**
     * Test formatResults with different modes
     */
    public function testFormatResultsWithDifferentModes(): void
    {
        $index = new Index();
        $query = new Query($index);

        $formatter1 = function ($results) {
            return $results;
        };
        $formatter2 = function ($results) {
            return $results;
        };
        $formatter3 = function ($results) {
            return $results;
        };

        // Test APPEND mode (default)
        $query->formatResults($formatter1);
        $this->assertCount(1, $query->getResultFormatters());

        $query->formatResults($formatter2, Query::APPEND);
        $this->assertCount(2, $query->getResultFormatters());

        // Test PREPEND mode
        $query->formatResults($formatter3, Query::PREPEND);
        $this->assertCount(3, $query->getResultFormatters());

        // Test OVERWRITE mode
        $query->formatResults($formatter1, Query::OVERWRITE);
        $this->assertCount(1, $query->getResultFormatters());
    }

    /**
     * Test formatResults with null formatter and non-overwrite mode throws exception
     */
    public function testFormatResultsWithNullFormatterNonOverwriteMode(): void
    {
        $index = new Index();
        $query = new Query($index);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$formatter can be null only when $mode is overwrite.');
        $query->formatResults(null, Query::APPEND); // Should throw exception
    }

    /**
     * Test setRepository with non-Index object throws assertion error
     */
    public function testSetRepositoryWithNonIndex(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $query = new Query(new Index());

        $this->expectException(AssertionError::class);
        $query->setRepository($repository);
    }

    /**
     * Test orderBy with numeric key array (already sorted format)
     */
    public function testOrderByWithNumericKeyArray(): void
    {
        $index = new Index();
        $query = new Query($index);

        $order = [
            ['title' => 'asc'],
            ['created' => 'desc'],
        ];

        $query->orderBy($order);

        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);
    }

    /**
     * Test orderBy with closure
     */
    public function testOrderByWithClosure(): void
    {
        $index = new Index();
        $query = new Query($index);

        $orderClosure = function () {
            return ['title' => 'asc', 'created' => 'desc'];
        };

        $query->orderBy($orderClosure);

        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);
    }

    /**
     * Test applyOptions with all valid options
     */
    public function testApplyOptionsWithAllValidOptions(): void
    {
        $index = new Index();
        $query = new Query($index);

        $options = [
            'fields' => ['title', 'body'],
            'conditions' => ['published' => true],
            'limit' => 25,
            'order' => ['title' => 'asc'],
            'offset' => 10,
            'page' => 2,
            'whitelist' => ['title', 'body'],
            'cache' => 'test_key',
            'finder' => 'published',
        ];

        $result = $query->applyOptions($options);
        $this->assertSame($query, $result);

        // Verify some options were applied
        $this->assertSame(25, $query->clause('limit'));
        // Page option overrides offset, so it should be calculated as (page-1)*limit = (2-1)*25 = 25
        $this->assertSame(25, $query->clause('offset'));
    }

    /**
     * Test select with overwrite flag
     */
    public function testSelectWithOverwrite(): void
    {
        $index = new Index();
        $query = new Query($index);

        // First selection
        $query->select(['title', 'body']);
        $fields = $query->clause('fields');
        $this->assertContains('title', $fields);
        $this->assertContains('body', $fields);

        // Second selection with overwrite=false (should append)
        $query->select(['created'], false);
        $fields = $query->clause('fields');
        $this->assertContains('title', $fields);
        $this->assertContains('created', $fields);

        // Third selection with overwrite=true (should replace)
        $query->select(['id'], true);
        $fields = $query->clause('fields');
        $this->assertNotContains('title', $fields);
        $this->assertContains('id', $fields);
    }

    /**
     * Test collapse with string parameter
     */
    public function testCollapseWithString(): void
    {
        $index = new Index();
        $query = new Query($index);

        $result = $query->collapse('category');
        $this->assertSame($query, $result);

        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);
    }

    /**
     * Test aggregate with array parameter
     */
    public function testAggregateWithArray(): void
    {
        $index = new Index();
        $query = new Query($index);

        $aggregations = [];
        foreach (['avg_price'] as $name) {
            $aggregations[$name] = new Avg($name);
            $aggregations[$name]->setField('price');
        }

        $result = $query->aggregate($aggregations);
        $this->assertSame($query, $result);

        $compiled = $query->compileQuery();
        $this->assertInstanceOf(ElasticaQuery::class, $compiled);
    }

    /**
     * Test searchOptions getter when no options set
     */
    public function testSearchOptionsGetter(): void
    {
        $index = new Index();
        $query = new Query($index);

        $options = $query->searchOptions();
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    /**
     * Test searchOptions setter and getter
     */
    public function testSearchOptionsSetterAndGetter(): void
    {
        $index = new Index();
        $query = new Query($index);

        $options = ['timeout' => '1s', 'routing' => 'user1'];
        $result = $query->searchOptions($options);
        $this->assertSame($query, $result);

        $retrievedOptions = $query->searchOptions();
        $this->assertSame($options, $retrievedOptions);
    }

    /**
     * Test _buildBoolQuery with null conditions
     */
    public function testBuildBoolQueryWithNullConditions(): void
    {
        $index = new Index();
        $query = new Query($index);

        // This should not throw an error
        $result = $query->andWhere(null);
        $this->assertSame($query, $result);
    }
}
