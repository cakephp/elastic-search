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
        $type = $this->getMockBuilder('Cake\ElasticSearch\Index')->getMock();
        $query = $this->getMockBuilder('Cake\ElasticSearch\Query')
            ->setConstructorArgs([$type])
            ->getMock();
        $query->expects($this->once())->method('repository')
            ->will($this->returnValue($type));

        $type->expects($this->once())
            ->method('entityClass')
            ->will($this->returnValue(__NAMESPACE__ . '\MyTestDocument'));
        $type->method('embedded')
            ->will($this->returnValue([]));

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
        $result = $this->getMockBuilder('Elastica\Result')
            ->setMethods(['getId', 'getData', 'getType'])
            ->disableOriginalConstructor()
            ->getMock();
        $result->method('getData')
            ->will($this->returnValue($data));
        $result->method('getId')
            ->will($this->returnValue(99));
        $result->method('getType')
            ->will($this->returnValue('things'));

        $elasticaSet->expects($this->once())
            ->method('current')
            ->will($this->returnValue($result));

        $document = $resultSet->current();
        $this->assertInstanceOf(__NAMESPACE__ . '\MyTestDocument', $document);
        $this->assertSame($data + ['id' => 99], $document->toArray());
        $this->assertFalse($document->dirty());
        $this->assertFalse($document->isNew());
        $this->assertEquals('things', $document->type());
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
        $type = $this->getMockBuilder('Cake\ElasticSearch\Index')->getMock();
        $type->method('embedded')
            ->will($this->returnValue([]));
        $query = $this->getMockBuilder('Cake\ElasticSearch\Query')
            ->setConstructorArgs([$type])
            ->getMock();

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
