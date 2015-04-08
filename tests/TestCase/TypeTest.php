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
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;

/**
 * Tests the Type class
 *
 */
class TypeTest extends TestCase
{

    /**
     * Tests that calling find will return a query object
     *
     * @return void
     */
    public function testFindAll()
    {
        $type = new Type();
        $query = $type->find('all');
        $this->assertInstanceOf('Cake\ElasticSearch\Query', $query);
        $this->assertSame($type, $query->repository());
    }

    /**
     * Test the default entityClass.
     *
     * @return void
     */
    public function testEntityClassDefault()
    {
        $type = new Type();
        $this->assertEquals('\Cake\ElasticSearch\Document', $type->entityClass());
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace
     *
     * @return void
     */
    public function testTableClassInApp()
    {
        $class = $this->getMockClass('Cake\ElasticSearch\Document');
        class_alias($class, 'App\Model\Document\TestUser');

        $type = new Type();
        $this->assertEquals(
            'App\Model\Document\TestUser',
            $type->entityClass('TestUser')
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the Plugin namespace when using plugin notation
     *
     * @return void
     */
    public function testTableClassInPlugin()
    {
        $class = $this->getMockClass('\Cake\ElasticSearch\Document');
        class_alias($class, 'MyPlugin\Model\Document\SuperUser');

        $type = new Type();
        $this->assertEquals(
            'MyPlugin\Model\Document\SuperUser',
            $type->entityClass('MyPlugin.SuperUser')
        );
    }

    /**
     * Tests the get method
     *
     * @return void
     */
    public function testGet()
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

        $document = $this->getMock('Elastica\Document', ['getData']);
        $internalType->expects($this->once())
            ->method('getDocument')
            ->with('foo', ['bar' => 'baz'])
            ->will($this->returnValue($document));

        $document->expects($this->once())->method('getData')
            ->will($this->returnValue(['a' => 'b']));

        $result = $type->get('foo', ['bar' => 'baz']);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertEquals(['a' => 'b'], $result->toArray());
        $this->assertFalse($result->dirty());
        $this->assertFalse($result->isNew());
    }
}
