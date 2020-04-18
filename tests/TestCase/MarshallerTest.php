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

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Marshaller;
use Cake\TestSuite\TestCase;
use TestApp\Model\Document\ProtectedArticle;
use TestApp\Model\Index\AccountsIndex;

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
    public $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');
        $this->index = new Index([
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
        $marshaller = new Marshaller($this->index);
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
        $this->index->getValidator()
            ->add('title', 'numbery', ['rule' => 'numeric']);

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertNull($result->title, 'Invalid fields are not set.');
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
        $this->assertNotEmpty($result->getErrors('title'), 'Should have an error.');
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
        $marshaller = new Marshaller($this->index);
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
        $this->index->setEntityClass(ProtectedArticle::class);

        $marshaller = new Marshaller($this->index);
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
        $this->index->getEventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->index);
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
        $this->index->getEventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->index);
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
        $this->index->embedOne('User');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertIsArray($result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user['username']);
    }

    /**
     * DataProvider for testOneEmbeddedOneWithOption
     *
     * @return array
     */
    public function oneEmbeddedOneWithOptionProvider()
    {
        return [
            // Test both embeds with options
            [['associated' => ['User' => [], 'Comment' => ['guard' => false]]]],
            // Test both embeds one with options the other without
            [['associated' => ['User' => [], 'Comment']]],
            // Test both embeds one without options
            [['associated' => ['User', 'Comment']]],
        ];
    }

    /**
     * test marshalling a simple object with associated options
     *
     * @dataProvider oneEmbeddedOneWithOptionProvider
     * @param array  $options  Options to pass to marshaller->one
     * @return void
     */
    public function testOneEmbeddedOneWithOptions($options)
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user' => [
                'username' => 'mark',
            ],
            'comment' => [
                'text' => 'this is great',
                'id' => 123,
            ],
        ];
        $this->index->embedOne('User');
        $this->index->embedOne('Comment');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, $options);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comment);
        $this->assertSame($data['user']['username'], $result->user->username);
        $this->assertSame($data['comment']['text'], $result->comment->text);
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
                'bad' => 'data',
            ],
        ];
        $this->index->embedMany('Comments');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertIsArray($result->comments);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * DataProvider for testOneEmbeddedManyWithOptions
     *
     * @return array
     */
    public function oneEmbeddedManyWithOptionsProvider()
    {
        return [
            // Test both embeds with options
            [['associated' => ['Comments' => ['guard' => false], 'Authors' => []]]],
            // Test both embeds one with options the other without
            [['associated' => ['Comments' => ['guard' => false], 'Authors']]],
            // Test both embeds one without options
            [['associated' => ['Comments', 'Authors']]],
        ];
    }

    /**
     * test marshalling a simple object.
     *
     * @dataProvider oneEmbeddedManyWithOptionsProvider
     * @param array  $options  Options to pass to marshaller->one
     * @return void
     */
    public function testOneEmbeddedManyWithOptions($options)
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data',
            ],
            'authors' => [
                ['name' => 'Bob Smith'],
                ['name' => 'Claire Muller'],
            ],
        ];
        $this->index->embedMany('Comments');
        $this->index->embedMany('Authors');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, $options);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertIsArray($result->comments);
        $this->assertIsArray($result->authors);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->authors[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->authors[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
        $this->assertTrue($result->authors[0]->isNew());
        $this->assertTrue($result->authors[1]->isNew());
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
            ],
        ];
        $marshaller = new Marshaller($this->index);
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
        $doc = $this->index->get(1);
        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($doc, $data);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertSame($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->isDirty('title'));
        $this->assertTrue($doc->isDirty('body'));
        $this->assertFalse($doc->isDirty('user_id'));
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
        $this->index->getValidator()
            ->add('title', 'numbery', ['rule' => 'numeric']);
        $doc = $this->index->get(1);

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($doc, $data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame('First article', $result->title, 'Invalid fields are not modified.');
        $this->assertNotEmpty($result->getErrors('title'), 'Should have an error.');
    }

    /**
     * Test merging data into existing records with a fieldlist
     *
     * @return void
     */
    public function testMergeFieldList()
    {
        $doc = $this->index->get(1);
        $doc->setAccess('*', false);

        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($doc, $data, ['fieldList' => ['title']]);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertNotEquals($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->isDirty('title'));
        $this->assertFalse($doc->isDirty('body'));
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
        $this->index->getEventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->index);
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
        $this->index->getEventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->index);
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
        $this->index->embedOne('User');
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $entity = new Document([
            'title' => 'Old',
            'user' => new Document(['username' => 'old'], ['markNew' => false]),
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->index);
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
        $this->index->embedOne('User');
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

        $marshaller = new Marshaller($this->index);
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
                'bad' => 'data',
            ],
        ];
        $this->index->embedMany('Comments');

        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
                new Document(['comment' => 'old'], ['markNew' => false]),
            ],
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertIsArray($result->comments);
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
                'bad' => 'data',
            ],
        ];
        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
            ],
        ], ['markNew' => false]);

        $this->index->embedMany('Comments');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertIsArray($result->comments);

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
        $marshaller = new Marshaller($this->index);
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
        $marshaller = new Marshaller($this->index);
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
        $doc = $this->index->get(1);
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
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0]['title'], $result[0]->title);
        $this->assertFalse($result[0]->isNew());
        $this->assertTrue($result[0]->isDirty());
        $this->assertTrue($result[0]->isDirty('title'));

        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->isDirty());
        $this->assertTrue($result[1]->isDirty('title'));
    }

    /**
     * Test that unknown entities are excluded from the results.
     *
     * @return void
     */
    public function testMergeManyDropsUnknownEntities()
    {
        $doc = $this->index->get(1);
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
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
        $this->assertTrue($result[0]->isNew());
        $this->assertTrue($result[0]->isDirty());
        $this->assertTrue($result[0]->isDirty('title'));

        $this->assertEquals($data[1], $result[1]->toArray());
        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->isDirty());
        $this->assertTrue($result[1]->isDirty('title'));
    }

    /**
     * Ensure that only entities are updated.
     *
     * @return void
     */
    public function testMergeManyBadEntityData()
    {
        $doc = $this->index->get(1);
        $entities = ['text', ['herp' => 'derp']];

        $data = [
            [
                'title' => 'New first',
            ],
        ];
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(1, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
    }

    /**
     * test marshalling One with multi level embed
     *
     * @return void
     */
    public function testMarshallOneMultiLevelEmbed()
    {
        Configure::write('App.namespace', 'TestApp');

        $data = [
            'address' => '123 West Street',
            'users' => [
                [
                    'first_name' => 'Mark',
                    'last_name' => 'Story',
                    'user_type' => [
                        'label' => 'Admin',
                    ],
                ],
                ['first_name' => 'Clare', 'last_name' => 'Smith'],
            ],
        ];
        $options = [
            'associated' => [
                'User' => [
                    'associated' => [
                        'UserType' => [],
                    ],
                ],
            ],
        ];

        $index = new AccountsIndex();

        $marshaller = new Marshaller($index);
        $result = $marshaller->one($data, $options);

        $this->assertCount(2, $result->users);
        $this->assertSame('123 West Street', $result->address);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result->users[0]);
        $this->assertSame('Mark', $result->users[0]->first_name);
        $this->assertSame('Story', $result->users[0]->last_name);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result->users[1]);
        $this->assertSame('Clare', $result->users[1]->first_name);
        $this->assertSame('Smith', $result->users[1]->last_name);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->users[0]->user_type);
        $this->assertSame('Admin', $result->users[0]->user_type->label);
    }

    /**
     * test marshalling One with multi level embed (with AccessibleFields)
     *
     * @return void
     */
    public function testMarshallOneMultiLevelEmbedWithAccessibleFields()
    {
        Configure::write('App.namespace', 'TestApp');

        $data = [
            'address' => '123 West Street',
            'remove_this' => 'something',
            'users' => [
                [
                    'first_name' => 'Mark',
                    'last_name' => 'Story',
                    'user_type' => [
                        'label' => 'Admin',
                        'level' => 21,
                    ],
                ],
                ['first_name' => 'Clare', 'last_name' => 'Smith'],
            ],
        ];
        $options = [
            'accessibleFields' => ['remove_this' => false],
            'associated' => [
                'User' => [
                    'accessibleFields' => ['last_name' => false],
                    'associated' => [
                        'UserType' => [
                            'accessibleFields' => ['level' => false],
                        ],
                    ],
                ],
            ],
        ];

        $index = new AccountsIndex();

        $marshaller = new Marshaller($index);
        $result = $marshaller->one($data, $options);

        $this->assertCount(2, $result->users);
        $this->assertNull($result->remove_this);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result->users[0]);
        $this->assertNull($result->users[0]->last_name);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result->users[1]);
        $this->assertNull($result->users[1]->last_name);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->users[0]->user_type);
        $this->assertNull($result->users[0]->user_type->level);
    }

    /**
     * test marshalling Many with multi level embed
     *
     * @return void
     */
    public function testMarshallManyMultiLevelEmbed()
    {
        Configure::write('App.namespace', 'TestApp');

        $data = [
            [
                'address' => '123 West Street',
                'users' => [
                    [
                        'first_name' => 'Mark',
                        'last_name' => 'Story',
                        'user_type' => [
                            'label' => 'Admin',
                        ],
                    ],
                    ['first_name' => 'Clare', 'last_name' => 'Smith'],
                ],
            ],
            [
                'address' => '87 Grant Avenue',
                'users' => [
                    [
                        'first_name' => 'Colin',
                        'last_name' => 'Thomas',
                        'user_type' => [
                            'label' => 'Admin',
                        ],
                    ],
                ],
            ],
        ];
        $options = [
            'associated' => [
                'User' => [
                    'associated' => [
                        'UserType' => [],
                    ],
                ],
            ],
        ];

        $index = new AccountsIndex();

        $marshaller = new Marshaller($index);
        $result = $marshaller->many($data, $options);

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[0]->users);
        $this->assertCount(1, $result[1]->users);
        $this->assertSame('123 West Street', $result[0]->address);
        $this->assertSame('87 Grant Avenue', $result[1]->address);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result[0]->users[0]);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result[0]->users[1]);
        $this->assertInstanceOf('TestApp\Model\Document\User', $result[1]->users[0]);
        $this->assertSame('Mark', $result[0]->users[0]->first_name);
        $this->assertSame('Story', $result[0]->users[0]->last_name);
        $this->assertSame('Clare', $result[0]->users[1]->first_name);
        $this->assertSame('Smith', $result[0]->users[1]->last_name);
        $this->assertSame('Colin', $result[1]->users[0]->first_name);
        $this->assertSame('Thomas', $result[1]->users[0]->last_name);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[0]->users[0]->user_type);
        $this->assertSame('Admin', $result[0]->users[0]->user_type->label);
    }
}
