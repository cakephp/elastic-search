<?php

namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\ResultSet;
use Cake\TestSuite\TestCase;

class MyTestDocument extends Document
{
}

/**
 * Tests the ResultSet class
 *
 */
class ResultSetTest extends TestCase
{

    /**
     * Tests the construction process
     *
     * @return void
     */
    public function testConstructor()
    {
        $elasticaSet = $this->getMockBuilder('Elastica\ResultSet')
            ->disableOriginalConstructor()
            ->getMock();
        $type = $this->getMock('Cake\ElasticSearch\Type');
        $query = $this->getMock('Cake\ElasticSearch\Query', [], [$type]);
        $query->expects($this->once())->method('repository')
            ->will($this->returnValue($type));

        $type->expects($this->once())
            ->method('entityClass')
            ->will($this->returnValue(__NAMESPACE__ . '\MyTestDocument'));
        return [new ResultSet($elasticaSet, $query), $elasticaSet];
    }

    /**
     * Tests that calling current will wrap the result using the provided entity
     * class
     *
     * @depends testConstructor
     * @return void
     */
    public function testCurrent($resultSets)
    {
        list($resultSet, $elasticaSet) = $resultSets;
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMock('Elastica\Result', ['getData'], [[]]);
        $result->expects($this->once())->method('getData')
            ->will($this->returnValue($data));

        $elasticaSet->expects($this->once())->method('current')
            ->will($this->returnValue($result));
        $document = $resultSet->current();
        $this->assertInstanceOf(__NAMESPACE__ . '\MyTestDocument', $document);
        $this->assertSame($data, $document->toArray());
        $this->assertFalse($document->dirty());
        $this->assertFalse($document->isNew());
    }

    /**
     * Tests that the original ResultSet's methods are accessible
     *
     * @return void
     */
    public function testDecoratedMethods()
    {
        $methods = get_class_methods('Elastica\ResultSet');
        $exclude = [
            '__construct', 'offsetSet', 'offsetGet', 'offsetExists', 'offsetUnset',
            'current', 'next', 'key', 'valid', 'rewind', 'create', 'setClass'
        ];
        $methods = array_diff($methods, $exclude);

        $elasticaSet = $this->getMockBuilder('Elastica\ResultSet')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
            $type = $this->getMock('Cake\ElasticSearch\Type');
        $query = $this->getMock('Cake\ElasticSearch\Query', [], [$type]);
        $query->expects($this->once())->method('repository')
            ->will($this->returnValue($type));

        $requireParam = ['getAggregation' => 'foo'];
        $resultSet = new ResultSet($elasticaSet, $query);
        foreach ($methods as $method) {
            $expect = $elasticaSet->expects($this->once())->method($method);
            $param = null;

            if (isset($requireParam[$method])) {
                $expect->with($requireParam[$method]);
                $param = $requireParam[$method];
            }

            $return = 'something';
            $expect->will($this->returnValue($return));

            $this->assertSame($return, $resultSet->{$method}($param));
        }
    }
}
