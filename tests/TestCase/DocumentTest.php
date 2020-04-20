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

use Cake\ElasticSearch\Document;
use Cake\TestSuite\TestCase;

/**
 * Tests the Document class
 */
class DocumentTest extends TestCase
{
    /**
     * Tests constructing a document
     *
     * @return void
     */
    public function testConstructorArray()
    {
        $data = ['foo' => 1, 'bar' => 2];
        $document = new Document($data);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that constructing a document with a Elastica Result will
     * use the returned data out of it
     *
     * @return void
     */
    public function testConstructorWithResult()
    {
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMockBuilder('Elastica\Result')
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $result->expects($this->once())->method('getData')
            ->will($this->returnValue($data));
        $document = new Document($result);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that the result object can be passed in the options array
     *
     * @return void
     */
    public function testConstructorWithResultAsOption()
    {
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMockBuilder('Elastica\Result')
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $document = new Document($data, ['result' => $result]);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that creating a document without a result object will
     * make the proxy functions return their default
     *
     * @return void
     */
    public function testNewWithNoResult()
    {
        $document = new Document();
        $this->assertNull($document->type());
        $this->assertSame(1, $document->version());
        $this->assertEquals([], $document->highlights());
        $this->assertEquals([], $document->explanation());
    }

    /**
     * Tests that passing a result object in the constructor makes
     * the proxy the functions return the right value
     *
     * @return void
     */
    public function testTypeWithResult()
    {
        $result = $this->getMockBuilder('Elastica\Result')
            ->setMethods(['getData', 'getId', 'getType', 'getVersion', 'getHighlights', 'getExplanation'])
            ->disableOriginalConstructor()
            ->getMock();
        $data = ['a' => 'b'];

        $result
            ->method('getData')
            ->will($this->returnValue($data));

        $result
            ->method('getId')
            ->will($this->returnValue(1));

        $result
            ->method('getType')
            ->will($this->returnValue('things'));

        $result
            ->method('getVersion')
            ->will($this->returnValue(3));

        $result
            ->method('getHighlights')
            ->will($this->returnValue(['highlights array']));

        $result
            ->method('getExplanation')
            ->will($this->returnValue(['explanation array']));

        $document = new Document($result);
        $this->assertSame($data + ['id' => 1], $document->toArray());
        $this->assertEquals('things', $document->type());
        $this->assertEquals(3, $document->version());
        $this->assertEquals(['highlights array'], $document->highlights());
        $this->assertEquals(['explanation array'], $document->explanation());
    }
}
