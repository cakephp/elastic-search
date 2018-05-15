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

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Index;
use Cake\TestSuite\TestCase;

/**
 * Tests features around embeded documents.
 *
 */
class EmbeddedDocumentTest extends TestCase
{
    public $fixtures = ['plugin.cake/elastic_search.profiles'];

    public function setUp()
    {
        parent::setUp();
        $this->index = new Index([
            'name' => 'profiles',
            'connection' => ConnectionManager::get('test')
        ]);
    }

    /**
     * Test defining 1:1 embedded documents.
     *
     * @return void
     */
    public function testEmbedOne()
    {
        $this->assertNull($this->index->embedOne('Address'));
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertEquals('\Cake\ElasticSearch\Document', $assocs[0]->entityClass());
        $this->assertEquals('address', $assocs[0]->property());
    }

    /**
     * Test fetching with embedded documents.
     *
     * @return void
     */
    public function testGetWithEmbedOne()
    {
        $this->index->embedOne('Address');
        $result = $this->index->get(1);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->address);
        $this->assertEquals('123 street', $result->address->street);
    }

    /**
     * Test fetching with embedded documents.
     *
     * @return void
     */
    public function testFindWithEmbedOne()
    {
        $this->index->embedOne('Address');
        $result = $this->index->find()->where(['username' => 'mark']);
        $rows = $result->toArray();
        $this->assertCount(1, $rows);
    }

    /**
     * Test fetching with embedded has many documents.
     *
     * @return void
     */
    public function testGetWithEmbedMany()
    {
        $this->index->embedMany('Address');
        $result = $this->index->get(3);
        $this->assertInternalType('array', $result->address);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->address[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->address[1]);
    }

    /**
     * Test fetching with embedded documents.
     *
     * @return void
     */
    public function testFindWithEmbedMany()
    {
        $this->index->embedMany('Address');
        $result = $this->index->find()->where(['username' => 'sara']);
        $rows = $result->toArray();

        $this->assertCount(1, $rows);
        $this->assertInternalType('array', $rows[0]->address);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $rows[0]->address[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $rows[0]->address[1]);
    }
}
