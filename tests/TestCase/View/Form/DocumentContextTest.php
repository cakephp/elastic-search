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
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\View\Form;

use Cake\Collection\Collection;
use Cake\Network\Request;
use Cake\ElasticSearch\Document;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;
use Cake\ElasticSearch\View\Form\DocumentContext;
use ArrayIterator;
use ArrayObject;

/**
 * Test stub.
 */
class Article extends Document
{

}

/**
 * Test case for the DocumentContext
 */
class DocumentContextTest extends TestCase
{
    /**
     * Fixtures to use.
     *
     * @var array
     */
    public $fixtures = ['plugin.cake/elastic_search.articles'];

    /**
     * setup method.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->request = new Request();
    }

    /**
     * Test getting primary key data.
     *
     * @return void
     */
    public function testPrimaryKey()
    {
        $row = new Article();
        $context = new DocumentContext($this->request, [
            'entity' => $row,
        ]);
        $this->assertEquals(['id'], $context->primaryKey());
    }

    /**
     * Test isPrimaryKey
     *
     * @return void
     */
    public function testIsPrimaryKey()
    {
        $row = new Article();
        $context = new DocumentContext($this->request, [
            'entity' => $row,
        ]);
        $this->assertTrue($context->isPrimaryKey('id'));
        $this->assertFalse($context->isPrimaryKey('title'));
        $this->assertTrue($context->isPrimaryKey('1.id'));
        $this->assertTrue($context->isPrimaryKey('Articles.1.id'));
        $this->assertTrue($context->isPrimaryKey('comments.0.id'));
        $this->assertTrue($context->isPrimaryKey('1.comments.0.id'));
        $this->assertFalse($context->isPrimaryKey('1.comments.0.comment'));
        $this->assertFalse($context->isPrimaryKey('Articles.1.comments.0.comment'));
    }

    /**
     * Test isCreate on a single entity.
     *
     * @return void
     */
    public function testIsCreateSingle()
    {
        $row = new Article();
        $context = new DocumentContext($this->request, [
            'entity' => $row,
        ]);
        $this->assertTrue($context->isCreate());

        $row->isNew(false);
        $this->assertFalse($context->isCreate());

        $row->isNew(true);
        $this->assertTrue($context->isCreate());
    }

    /**
     * Data provider for testing collections.
     *
     * @return array
     */
    public static function collectionProvider()
    {
        $one = new Article([
            'title' => 'First post',
            'body' => 'Stuff',
            'user' => new Document(['username' => 'mark'])
        ]);
        $one->errors('title', 'Required field');

        $two = new Article([
            'title' => 'Second post',
            'body' => 'Some text',
            'user' => new Document(['username' => 'jose'])
        ]);
        $two->errors('body', 'Not long enough');

        return [
            'array' => [[$one, $two]],
            'basic iterator' => [new ArrayObject([$one, $two])],
            'array iterator' => [new ArrayIterator([$one, $two])],
            'collection' => [new Collection([$one, $two])],
        ];
    }


    /**
     * Test isCreate on a collection.
     *
     * @dataProvider collectionProvider
     * @return void
     */
    public function testIsCreateCollection($collection)
    {
        $context = new DocumentContext($this->request, [
            'entity' => $collection,
        ]);
        $this->assertTrue($context->isCreate());
    }

    /**
     * Test reading data.
     *
     * @return void
     */
    public function testValBasic()
    {
        $row = new Article([
            'title' => 'Test entity',
            'body' => 'Something new'
        ]);
        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'table' => 'articles',
        ]);
        $result = $context->val('title');
        $this->assertEquals($row->title, $result);

        $result = $context->val('body');
        $this->assertEquals($row->body, $result);

        $result = $context->val('nope');
        $this->assertNull($result);
    }

    /**
     * Test reading data from embeddded docs.
     *
     * @return void
     */
    public function testValEmbeddedDocs()
    {
        $row = new Article([
            'title' => 'Test entity',
            'body' => 'Something new',
            'user' => new Document(['username' => 'sarah']),
            'comments' => [
                new Document(['comment' => 'first comment']),
                new Document(['comment' => 'second comment']),
            ]
        ]);
        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'table' => 'articles',
        ]);
        $result = $context->val('user.username');
        $this->assertEquals($result, $row->user->username);

        $result = $context->val('comments.0.comment');
        $this->assertEquals($result, $row->comments[0]->comment);

        $result = $context->val('comments.1.comment');
        $this->assertEquals($result, $row->comments[1]->comment);

        $result = $context->val('comments.2.comment');
        $this->assertNull($result);
    }

    /**
     * Test operations on a collection of entities.
     *
     * @dataProvider collectionProvider
     * @return void
     */
    public function testValOnCollections($collection)
    {
        $context = new DocumentContext($this->request, [
            'entity' => $collection,
            'type' => 'articles',
        ]);

        $result = $context->val('0.title');
        $this->assertEquals('First post', $result);

        $result = $context->val('0.user.username');
        $this->assertEquals('mark', $result);

        $result = $context->val('1.title');
        $this->assertEquals('Second post', $result);

        $result = $context->val('1.user.username');
        $this->assertEquals('jose', $result);

        $this->assertNull($context->val('nope'));
        $this->assertNull($context->val('99.title'));
    }

    /**
     * Test fields being required by validation.
     *
     * @return void
     */
    public function testIsRequrired()
    {
        $this->markTestIncomplete();
    }

    /**
     * Test fields being required by validation.
     *
     * @return void
     */
    public function testIsRequriredAlternateValidator()
    {
        $this->markTestIncomplete();
    }
}
