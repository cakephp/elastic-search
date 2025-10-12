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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.0.1
 * @license   https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Association\EmbedMany;
use Cake\ElasticSearch\Association\EmbedOne;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\TestSuite\TestCase;
use TestApp\Model\Document\Address;

/**
 * Tests features around embeded documents.
 */
class EmbeddedDocumentTest extends TestCase
{
    public array $fixtures = ['plugin.Cake/ElasticSearch.Profiles'];

    protected Index $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = new Index(
            [
            'name' => 'profiles',
            'connection' => ConnectionManager::get('test'),
            ],
        );
    }

    /**
     * Test defining 1:1 embedded documents.
     */
    public function testEmbedOne(): void
    {
        $this->assertNull($this->index->embedOne('Address'));
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertInstanceOf(EmbedOne::class, $assocs[0]);
        $this->assertSame(Address::class, $assocs[0]->getEntityClass());
        $this->assertSame(Index::class, $assocs[0]->getIndexClass());
        $this->assertSame('address', $assocs[0]->getProperty());
    }

    /**
     * Test fetching with embedded documents.
     */
    public function testGetWithEmbedOne(): void
    {
        $this->index->embedOne('Address');
        $result = $this->index->get(1);
        $this->assertInstanceOf(Document::class, $result->address);
        $this->assertSame('123 street', $result->address->street);
    }

    /**
     * DataProvider for different embed types
     */
    public static function embedTypeProvider(): array
    {
        return [
            // Test to make sure entityClass is derived from Alias
            [[], Address::class],

            // Test to make sure simple classname entityClass works
            [['entityClass' => 'Address'], Address::class],

            // Test to make sure full namespace on entityClass works
            [['entityClass' => Address::class], Address::class],
        ];
    }

    /**
     * Test fetching with EmbedOne documents.
     *
     * @dataProvider embedTypeProvider
     * @param array  $options  Options to pass to embed
     * @param string $expected Expected type
     */
    public function testGetWithEmbedOneType(array $options, string $expected): void
    {
        Configure::write('App.namespace', 'TestApp');
        $this->index->embedOne('Address', $options);
        $result = $this->index->get(1);
        $this->assertInstanceOf($expected, $result->address);
        $this->assertSame('123 street', $result->address->street);
    }

    /**
     * Test fetching with embedded documents.
     */
    public function testFindWithEmbedOne(): void
    {
        $this->index->embedOne('Address');
        $result = $this->index->find()->where(['username' => 'mark']);
        $rows = $result->toArray();
        $this->assertCount(1, $rows);
    }

    /**
     * Test defining many embedded documents.
     */
    public function testEmbedMany(): void
    {
        $this->assertNull($this->index->embedMany('Address'));
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertInstanceOf(EmbedMany::class, $assocs[0]);
        $this->assertSame(Address::class, $assocs[0]->getEntityClass());
        $this->assertSame(Index::class, $assocs[0]->getIndexClass());
        $this->assertSame('address', $assocs[0]->getProperty());
    }

    /**
     * Test fetching with embedded has many documents.
     */
    public function testGetWithEmbedMany(): void
    {
        $this->index->embedMany('Address');
        $result = $this->index->get(3);
        $this->assertIsArray($result->address);
        $this->assertInstanceOf(Document::class, $result->address[0]);
        $this->assertInstanceOf(Document::class, $result->address[1]);
    }

    /**
     * Test fetching with EmbedMany documents.
     *
     * @dataProvider embedTypeProvider
     * @param array  $options  Options to pass to embed
     * @param string $expected Expected type
     */
    public function testGetWithEmbedManyType(array $options, string $expected): void
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
     */
    public function testFindWithEmbedMany(): void
    {
        $this->index->embedMany('Address');
        $result = $this->index->find()->where(['username' => 'sara']);
        $rows = $result->toArray();

        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]->address);
        $this->assertInstanceOf(Document::class, $rows[0]->address[0]);
        $this->assertInstanceOf(Document::class, $rows[0]->address[1]);
    }

    /**
     * Test embed a missing document, so a generic one
     * is used.
     */
    public function testEmbededMissingDocument(): void
    {
        $this->index->embedOne('InvalidDocumentName');
        $assocs = $this->index->embedded();
        $this->assertCount(1, $assocs);
        $this->assertSame(Document::class, $assocs[0]->getEntityClass());
        $this->assertSame('invalid_document_name', $assocs[0]->getProperty());
    }
}
