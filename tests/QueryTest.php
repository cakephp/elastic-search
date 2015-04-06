<?php

namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Datasource\Connection;
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
            ->will($this->returnCallback(function($query) use ($result) {
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
}
