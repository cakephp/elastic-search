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
}
