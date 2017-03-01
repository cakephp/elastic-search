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

use ArrayIterator;
use ArrayObject;
use Cake\Collection\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Type;
use Cake\ElasticSearch\TypeRegistry;
use Cake\ElasticSearch\View\Form\DocumentContext;
use Cake\Network\Request;
use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;

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
    public $fixtures = [
        'plugin.cake/elastic_search.articles',
        'plugin.cake/elastic_search.profiles'
    ];

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
     * teardown method.
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        TypeRegistry::clear();
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
        $articles = $this->setupType();
        $entity = new Document(['title' => 'test']);

        $context = new DocumentContext($this->request, [
            'entity' => $entity,
            'type' => $articles,
        ]);
        $this->assertTrue($context->isRequired('title'));
        $this->assertFalse($context->isRequired('body'));
        $this->assertFalse($context->isRequired('no_validate'));
    }

    /**
     * Test fields being required by validation.
     *
     * @return void
     */
    public function testIsRequriredAlternateValidator()
    {
        $articles = $this->setupType();
        $entity = new Document(['title' => 'test']);

        $context = new DocumentContext($this->request, [
            'entity' => $entity,
            'type' => $articles,
            'validator' => 'alternate'
        ]);
        $this->assertFalse($context->isRequired('title'));
        $this->assertTrue($context->isRequired('body'));
        $this->assertFalse($context->isRequired('no_validate'));
    }

    /**
     * Test error operations on a collection of entities.
     *
     * @dataProvider collectionProvider
     * @return void
     */
    public function testErrorsOnCollections($collection)
    {
        $context = new DocumentContext($this->request, [
            'entity' => $collection,
            'tyupe' => 'articles',
        ]);

        $this->assertTrue($context->hasError('0.title'));
        $this->assertEquals(['Required field'], $context->error('0.title'));
        $this->assertFalse($context->hasError('0.body'));

        $this->assertFalse($context->hasError('1.title'));
        $this->assertEquals(['Not long enough'], $context->error('1.body'));
        $this->assertTrue($context->hasError('1.body'));

        $this->assertFalse($context->hasError('nope'));
        $this->assertFalse($context->hasError('99.title'));
    }

    /**
     * Test error
     *
     * @return void
     */
    public function testError()
    {
        $articles = $this->setupType();

        $row = new Article([
            'title' => 'My title',
            'user' => new Document(['username' => 'Mark']),
        ]);
        $row->errors('title', []);
        $row->errors('body', 'Gotta have one');
        $row->errors('user_id', ['Required field']);

        $row->user->errors('username', ['Required']);

        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'type' => $articles,
        ]);

        $this->assertEquals([], $context->error('title'));

        $expected = ['Gotta have one'];
        $this->assertEquals($expected, $context->error('body'));

        $expected = ['Required'];
        $this->assertEquals($expected, $context->error('user.username'));
    }

    /**
     * Test error on associated entities.
     *
     * @return void
     */
    public function testErrorAssociatedHasMany()
    {
        $articles = $this->setupType();

        $row = new Article([
            'title' => 'My title',
            'comments' => [
                new Document(['comment' => '']),
                new Document(['comment' => 'Second comment']),
            ]
        ]);
        $row->comments[0]->errors('comment', ['Is required']);
        $row->comments[0]->errors('article_id', ['Is required']);

        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'table' => $articles,
            'validator' => 'default',
        ]);

        $this->assertEquals([], $context->error('title'));
        $this->assertEquals([], $context->error('comments.0.user_id'));
        $this->assertEquals([], $context->error('comments.0'));
        $this->assertEquals(['Is required'], $context->error('comments.0.comment'));
        $this->assertEquals(['Is required'], $context->error('comments.0.article_id'));
        $this->assertEquals([], $context->error('comments.1'));
        $this->assertEquals([], $context->error('comments.1.comment'));
        $this->assertEquals([], $context->error('comments.1.article_id'));
    }

    /**
     * Test getting fieldnames.
     *
     * @return void
     */
    public function testFieldNames()
    {
        $articles = $this->setupType();
        $context = new DocumentContext($this->request, [
            'entity' => new Document([]),
            'type' => 'articles',
        ]);
        $result = $context->fieldNames();
        $this->assertContains('title', $result);
        $this->assertContains('body', $result);
        $this->assertContains('user_id', $result);
    }

    /**
     * Test type() basic
     *
     * @return void
     */
    public function testType()
    {
        $articles = $this->setupType();

        $row = new Article([
            'title' => 'My title',
            'body' => 'Some content',
        ]);
        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'type' => $articles,
        ]);

        $this->assertEquals('string', $context->type('title'));
        $this->assertEquals('string', $context->type('body'));
        $this->assertEquals('integer', $context->type('user_id'));
        $this->assertNull($context->type('nope'));
    }

    /**
     * Test type() nested fields
     *
     * @return void
     */
    public function testTypeNestedFields()
    {
        $profiles = new Type([
            'connection' => ConnectionManager::get('test'),
            'name' => 'profiles',
        ]);

        $row = new Document([]);
        $context = new DocumentContext($this->request, [
            'entity' => $row,
            'type' => $profiles,
        ]);

        $this->assertEquals('string', $context->type('username'));
        $this->assertEquals('string', $context->type('address.city'));
        $this->assertNull($context->type('nope'));
    }

    /**
     * Setup an articles type for testing against.
     *
     * @return \Cake\ElasticSearch\Type
     */
    protected function setupType()
    {
        $articles = TypeRegistry::get('Articles');
        $articles->embedOne('User');
        $articles->embedMany('Comments');

        $articles->validator()->add('title', 'notblank', [
            'rule' => 'notBlank'
        ]);

        $validator = new Validator();
        $validator->add('body', 'notblank', [
            'rule' => 'notBlank'
        ]);
        $articles->validator('alternate', $validator);

        return $articles;
    }
}
