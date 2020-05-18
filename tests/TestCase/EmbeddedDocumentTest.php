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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link      http://cakephp.org CakePHP(tm) Project
 * @since     0.0.1
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Index;
use Cake\TestSuite\TestCase;

/**
 * Tests features around embeded documents.
 */
class EmbeddedDocumentTest extends TestCase
{
    public $fixtures = ['plugin.Cake/ElasticSearch.Profiles'];

    public function setUp(): void
    {
        parent::setUp();
        $this->index = new Index(
            [
            'name' => 'profiles',
            'connection' => ConnectionManager::get('test'),
            ]
        );
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
        $this->assertInstanceOf('Cake\ElasticSearch\Association\EmbedOne', $assocs[0]);
        $this->assertEquals('TestApp\Model\Document\Address', $assocs[0]->getEntityClass());
        $this->assertEquals('Cake\ElasticSearch\Index', $assocs[0]->getIndexClass());
        $this->assertEquals('address', $assocs[0]->getProperty());
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
     * DataProvider for different embed types
     *
     * @return array
     */

    public function embedTypeProvider()
    {
        return [
            // Test to make sure entityClass is derived from Alias
            [[], 'TestApp\Model\Document\Address'],

            // Test to make sure simple classname entityClass works
            [['entityClass' => 'Address'], 'TestApp\Model\Document\Address'],

            // Test to make sure full namespace on entityClass works
            [['entityClass' => 'TestApp\Model\Document\Address'], 'TestApp\Model\Document\Address'],
        ];
    }

    /**
     * Test fetching with EmbedOne documents.
     *
     * @dataProvider embedTypeProvider
     * @param array  $options  Options to pass to embed
     * @param string $expected Expected type
     * @return void
     */
    public function testGetWithEmbedOneType($options, $expected)
    {
        Configure::write('App.namespace', 'TestApp');
        $this->index->embedOne('Address', $options);
        $result = $this->index->get(1);
        $this->assertInstanceOf($expected, $result->address);
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
     * Test defining many embedded documents.
     *
     * @return void
     */
    public function testEmbedMany()
    {
        $this->assertNull($this->index->embedMany('Address'));
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertInstanceOf('Cake\ElasticSearch\Association\EmbedMany', $assocs[0]);
        $this->assertEquals('TestApp\Model\Document\Address', $assocs[0]->getEntityClass());
        $this->assertEquals('Cake\ElasticSearch\Index', $assocs[0]->getIndexClass());
        $this->assertEquals('address', $assocs[0]->getProperty());
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
        $this->assertIsArray($result->address);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->address[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->address[1]);
    }

    /**
     * Test fetching with EmbedMany documents.
     *
     * @dataProvider embedTypeProvider
     * @param array  $options  Options to pass to embed
     * @param string $expected Expected type
     * @return void
     */
    public function testGetWithEmbedManyType($options, $expected)
    {
        Configure::write('App.namespace', 'TestApp');
        $this->index->embedMany('Address', $options);
        $result = $this->index->get(3);
        $this->assertIsArray($result->address);
        $this->assertInstanceOf($expected, $result->address[0]);
        $this->assertInstanceOf($expected, $result->address[1]);
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
        $this->assertIsArray($rows[0]->address);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $rows[0]->address[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $rows[0]->address[1]);
    }

    /**
     * Test embed a missing document, so a generic one
     * is used.
     *
     * @return void
     */
    public function testEmbededMissingDocument()
    {
        $this->index->embedOne('InvalidDocumentName');
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertEquals('Cake\ElasticSearch\Document', $assocs[0]->getEntityClass());
        $this->assertEquals('invalid_document_name', $assocs[0]->getProperty());
    }
}
