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

use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\TestSuite\TestCase;
use Elastica\Result;

/**
 * Tests the Document class
 */
class DocumentTest extends TestCase
{
    /**
     * Tests constructing a document
     */
    public function testConstructorArray(): void
    {
        $data = ['foo' => 1, 'bar' => 2];
        $document = new Document($data);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that constructing a document with a Elastica Result will
     * use the returned data out of it
     */
    public function testConstructorWithResult(): void
    {
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMockBuilder(Result::class)
            ->onlyMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $result->expects($this->once())->method('getData')
            ->willReturn($data);
        $document = new Document($result);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that the result object can be passed in the options array
     */
    public function testConstructorWithResultAsOption(): void
    {
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMockBuilder(Result::class)
            ->onlyMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMock();
        $document = new Document($data, ['result' => $result]);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that creating a document without a result object will
     * make the proxy functions return their default
     */
    public function testNewWithNoResult(): void
    {
        $document = new Document();
        $this->assertNull($document->index());
        $this->assertSame(1, $document->version());
        $this->assertEquals([], $document->highlights());
        $this->assertEquals([], $document->explanation());
    }

    /**
     * Tests that passing a result object in the constructor makes
     * the proxy the functions return the right value
     */
    public function testTypeWithResult(): void
    {
        $result = $this->getMockBuilder(Result::class)
            ->onlyMethods(['getData', 'getId', 'getIndex', 'getVersion', 'getHighlights', 'getExplanation'])
            ->disableOriginalConstructor()
            ->getMock();
        $data = ['a' => 'b'];

        $result
            ->method('getData')
            ->willReturn($data);

        $result
            ->method('getId')
            ->willReturn(1);

        $result
            ->method('getIndex')
            ->willReturn('things');

        $result
            ->method('getVersion')
            ->willReturn(3);

        $result
            ->method('getHighlights')
            ->willReturn(['highlights array']);

        $result
            ->method('getExplanation')
            ->willReturn(['explanation array']);

        $document = new Document($result);
        $this->assertSame($data + ['id' => 1], $document->toArray());
        $this->assertSame('things', $document->index());
        $this->assertSame(3, $document->version());
        $this->assertEquals(['highlights array'], $document->highlights());
        $this->assertEquals(['explanation array'], $document->explanation());
    }

    /**
     * Tests that the fields a document is built from are flagged as original on the
     * markClean shortcut, which is the path documents hydrated from a result set take
     */
    public function testConstructorFlagsOriginalFieldsWithoutSetters(): void
    {
        $Document = new Document(['name' => 'x'], ['markClean' => true, 'useSetters' => false]);

        $this->assertSame(['name'], $Document->getOriginalFields());
        $this->assertSame(['name' => 'x'], $Document->getOriginalValues());
        $this->assertFalse($Document->isDirty());
    }

    /**
     * Tests that the fields a document is built from are flagged as original when the
     * data goes through patch(), and that the document is still clean afterwards
     */
    public function testConstructorFlagsOriginalFieldsWithSetters(): void
    {
        $Document = new Document(['name' => 'x'], ['markClean' => true, 'useSetters' => true]);

        $this->assertSame(['name'], $Document->getOriginalFields());
        $this->assertSame(['name' => 'x'], $Document->getOriginalValues());
        $this->assertFalse($Document->isDirty());
    }

    /**
     * Tests that a new document created with data flags those fields as original,
     * the same way \Cake\ORM\Entity does
     */
    public function testConstructorFlagsOriginalFieldsForNewDocument(): void
    {
        $Document = new Document(['name' => 'x']);

        $this->assertSame(['name'], $Document->getOriginalFields());
        $this->assertTrue($Document->isDirty('name'));
    }

    /**
     * Tests that changing a field on a loaded document keeps the loaded value as its original
     */
    public function testGetOriginalAfterChange(): void
    {
        $Document = new Document(
            ['name' => 'x', 'tags' => ['a']],
            ['markClean' => true, 'useSetters' => false, 'markNew' => false],
        );
        $Document->name = 'changed';

        $this->assertSame('x', $Document->getOriginal('name'));
        $this->assertSame(['name' => 'x', 'tags' => ['a']], $Document->getOriginalValues());
        $this->assertSame(['name' => 'x'], $Document->extractOriginalChanged(['name', 'tags']));
    }

    /**
     * Tests that a document built from a search result flags the source fields and the id as original
     */
    public function testConstructorWithResultFlagsOriginalFields(): void
    {
        $Result = new Result(['_id' => '42', '_source' => ['name' => 'x', 'tags' => ['a']]]);
        $Document = new Document($Result, ['markClean' => true, 'useSetters' => false, 'markNew' => false]);

        $this->assertSame(['name', 'tags', 'id'], $Document->getOriginalFields());
        $this->assertSame(['name' => 'x', 'tags' => ['a'], 'id' => '42'], $Document->getOriginalValues());
    }
}
