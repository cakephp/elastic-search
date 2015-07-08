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
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Marshaller;
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;

/**
 * Test entity for mass assignment.
 */
class ProtectedArticle extends Document
{

    protected $_accessible = [
        'title' => true,
    ];
}


/**
 * Test case for the marshaller.
 */
class MarshallerTest extends TestCase
{
    /**
     * Fixtures for this test.
     *
     * @var array
     */
    public $fixtures = ['plugin.cake/elastic_search.articles'];

    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');
        $this->type = new Type([
            'connection' => $connection,
            'name' => 'articles',
        ]);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneSimple()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
    }

    /**
     * Test validation errors being set.
     *
     * @return void
     */
    public function testOneValidationErrorsSet()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->validator()
            ->add('title', 'numbery', ['rule' => 'numeric']);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertNull($result->title, 'Invalid fields are not set.');
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
        $this->assertNotEmpty($result->errors('title'), 'Should have an error.');
    }

    /**
     * test marshalling with fieldList
     *
     * @return void
     */
    public function testOneFieldList()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['fieldList' => ['title']]);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);
    }

    /**
     * test marshalling with accessibleFields
     *
     * @return void
     */
    public function testOneAccsesibleFields()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->entityClass(__NAMESPACE__ . '\ProtectedArticle');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);

        $result = $marshaller->one($data, ['accessibleFields' => ['body' => true]]);

        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertNull($result->user_id);
    }

    /**
     * test beforeMarshal event
     *
     * @return void
     */
    public function testOneBeforeMarshalEvent()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->type->eventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->type);
        $marshaller->one($data);

        $this->assertEquals(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     *
     * @return void
     */
    public function testOneBeforeMarshalEventMutateData()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->eventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);
        $this->assertEquals('Mutated', $result->title);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneEmbeddedOne()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $this->type->embedOne('User');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user['username']);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneEmbeddedMany()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $this->type->embedMany('Comments');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * Test converting multiple objects at once.
     *
     * @return void
     */
    public function testMany()
    {
        $data = [
            [
                'title' => 'Testing',
                'body' => 'Elastic text',
                'user_id' => 1,
            ],
            [
                'title' => 'Second article',
                'body' => 'Stretchy text',
                'user_id' => 2,
            ]
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->many($data);

        $this->assertCount(2, $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test merging data into existing records.
     *
     * @return void
     */
    public function testMerge()
    {
        $doc = $this->type->get(1);
        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($doc, $data);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertSame($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->dirty('title'));
        $this->assertTrue($doc->dirty('body'));
        $this->assertFalse($doc->dirty('user_id'));
        $this->assertFalse($doc->isNew(), 'Should not end up new');
    }

    /**
     * Test validation errors being set.
     *
     * @return void
     */
    public function testMergeValidationErrorsSet()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->validator()
            ->add('title', 'numbery', ['rule' => 'numeric']);
        $doc = $this->type->get(1);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($doc, $data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame('First article', $result->title, 'Invalid fields are not modified.');
        $this->assertNotEmpty($result->errors('title'), 'Should have an error.');
    }

    /**
     * Test merging data into existing records with a fieldlist
     *
     * @return void
     */
    public function testMergeFieldList()
    {
        $doc = $this->type->get(1);
        $doc->accessible('*', false);

        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($doc, $data, ['fieldList' => ['title']]);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertNotEquals($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->dirty('title'));
        $this->assertFalse($doc->dirty('body'));
    }

    /**
     * test beforeMarshal event
     *
     * @return void
     */
    public function testMergeBeforeMarshalEvent()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->type->eventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->type);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $marshaller->merge($doc, $data);

        $this->assertEquals(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     *
     * @return void
     */
    public function testMergeBeforeMarshalEventMutateData()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $this->type->eventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->type);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $result = $marshaller->merge($doc, $data);
        $this->assertEquals('Mutated', $result->title);
    }

    /**
     * test merge with an embed one
     *
     * @return void
     */
    public function testMergeEmbeddedOneExisting()
    {
        $this->type->embedOne('User');
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $entity = new Document([
            'title' => 'Old',
            'user' => new Document(['username' => 'old'], ['markNew' => false])
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertFalse($result->isNew(), 'Existing doc');
        $this->assertFalse($result->user->isNew(), 'Existing sub-doc');
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
    }

    /**
     * test merge when embedded documents don't exist
     *
     * @return void
     */
    public function testMergeEmbeddedOneMissing()
    {
        $this->type->embedOne('User');
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $entity = new Document([
            'title' => 'Old',
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
        $this->assertTrue($result->user->isNew(), 'Was missing, should now be new.');
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testMergeEmbeddedMany()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $this->type->embedMany('Comments');

        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
                new Document(['comment' => 'old'], ['markNew' => false]),
            ]
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertFalse($result->comments[0]->isNew());
        $this->assertFalse($result->comments[1]->isNew());
    }

    /**
     * test merge with some sub documents not existing.
     *
     * @return void
     */
    public function testMergeEmbeddedManySomeMissing()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
            ]
        ], ['markNew' => false]);

        $this->type->embedMany('Comments');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertSame('First comment', $result->comments[0]->comment);
        $this->assertFalse($result->comments[0]->isNew());

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertSame('Second comment', $result->comments[1]->comment);
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * Test that mergeMany will create new objects if the entity list is empty.
     *
     * @return void
     */
    public function testMergeManyAllNew()
    {
        $entities = [];
        $data = [
            [
                'title' => 'New first',
            ],
            [
                'title' => 'New second',
            ],
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Ensure that mergeMany uses the fieldList option.
     *
     * @return void
     */
    public function testMergeManyFieldList()
    {
        $entities = [];
        $data = [
            [
                'title' => 'New first',
                'body' => 'Nope',
            ],
            [
                'title' => 'New second',
                'body' => 'Nope',
            ],
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->mergeMany($entities, $data, ['fieldList' => ['title']]);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->body);
        $this->assertNull($result[1]->body);
    }

    /**
     * Ensure that mergeMany can merge a sparse data set.
     *
     * @return void
     */
    public function testMergeManySomeNew()
    {
        $doc = $this->type->get(1);
        $entities = [$doc];

        $data = [
            [
                'id' => 1,
                'title' => 'New first',
            ],
            [
                'title' => 'New second',
            ],
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0]['title'], $result[0]->title);
        $this->assertFalse($result[0]->isNew());
        $this->assertTrue($result[0]->dirty());
        $this->assertTrue($result[0]->dirty('title'));

        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->dirty());
        $this->assertTrue($result[1]->dirty('title'));
    }

    /**
     * Test that unknown entities are excluded from the results.
     *
     * @return void
     */
    public function testMergeManyDropsUnknownEntities()
    {
        $doc = $this->type->get(1);
        $entities = [$doc];

        $data = [
            [
                'id' => 2,
                'title' => 'New first',
            ],
            [
                'title' => 'New third',
            ],
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
        $this->assertTrue($result[0]->isNew());
        $this->assertTrue($result[0]->dirty());
        $this->assertTrue($result[0]->dirty('title'));

        $this->assertEquals($data[1], $result[1]->toArray());
        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->dirty());
        $this->assertTrue($result[1]->dirty('title'));
    }

    /**
     * Ensure that only entities are updated.
     *
     * @return void
     */
    public function testMergeManyBadEntityData()
    {
        $doc = $this->type->get(1);
        $entities = ['string', ['herp' => 'derp']];

        $data = [
            [
                'title' => 'New first',
            ],
        ];
        $marshaller = new Marshaller($this->type);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(1, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
    }
}
