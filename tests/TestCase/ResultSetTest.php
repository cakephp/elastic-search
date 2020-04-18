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

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\ResultSet;
use Cake\TestSuite\TestCase;
use TestApp\Model\Document\MyTestDocument;

/**
 * Tests the ResultSet class
 */
class ResultSetTest extends TestCase
{
    public $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

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
        $query->expects($this->once())->method('getRepository')
            ->will($this->returnValue($type));

        $type->expects($this->once())
            ->method('getEntityClass')
            ->will($this->returnValue(MyTestDocument::class));
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
        [$resultSet, $elasticaSet] = $resultSets;
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
        $this->assertInstanceOf(MyTestDocument::class, $document);
        $this->assertSame($data + ['id' => 99], $document->toArray());
        $this->assertFalse($document->isDirty());
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
            'current', 'next', 'key', 'valid', 'rewind', 'create', 'setClass',
            'count',
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

        $query->expects($this->once())->method('getRepository')
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

            $this->assertSame($return, $resultSet->{$method}($param), "The {$method} method did not have a matching return.");
        }
    }

    /**
     * Test stats related proxy methods
     *
     * @return void
     */
    public function testStatsProxies()
    {
        $index = new Index([
            'name' => 'articles',
            'connection' => ConnectionManager::get('test'),
        ]);
        $resultSet = $index->find()->all();
        $this->assertSame(2, $resultSet->count());
        $this->assertSame(0, $resultSet->countSuggests());
        $this->assertFalse($resultSet->hasTimedOut());
        $this->assertGreaterThan(-1, $resultSet->getTotalTime());
        $this->assertSame(2, $resultSet->getTotalHits());
        $this->assertSame([], $resultSet->getAggregations());
        $this->assertSame([], $resultSet->getSuggests());
    }

    /**
     * Test serialize/unserialize
     *
     * @return void
     */
    public function testSerialize()
    {
        $index = new Index([
            'name' => 'articles',
            'connection' => ConnectionManager::get('test'),
        ]);

        $resultSet = $index->find()->all();
        $serialized = serialize($resultSet);
        $outcome = unserialize($serialized);

        $this->assertEquals($resultSet->getResults(), $outcome->getResults());
        $this->assertEquals($resultSet->toArray(), $outcome->toArray());
    }
}
