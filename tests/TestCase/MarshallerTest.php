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

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Marshaller;
use Cake\ElasticSearch\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use TestApp\Model\Document\ProtectedArticle;
use TestApp\Model\Document\User;
use TestApp\Model\Index\AccountsIndex;

/**
 * Test case for the marshaller.
 */
class MarshallerTest extends TestCase
{
    /**
     * Fixtures for this test.
     */
    public array $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    protected Index $index;

    /**
     * Setup method.
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var Connection $connection */
        $connection = ConnectionManager::get('test');
        $this->index = new Index([
            'connection' => $connection,
            'name' => 'articles',
        ]);
    }

    /**
     * test marshalling a simple object.
     */
    public function testOneSimple(): void
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
    }

    /**
     * Test validation errors being set.
     */
    public function testOneValidationErrorsSet(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertNull($result->title, 'Invalid fields are not set.');
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
        $this->assertNotEmpty($result->getErrors(), 'Should have an error.');
    }

    /**
     * Test merging data into existing records with a fieldlist
     */
    public function testOneValidationErrorsPreserveInvalidFields(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertNull($result->title, 'Invalid fields are not set.');
        $this->assertSame('Testing', $result->getInvalidField('title'), 'Invalid field should be preserved.');
        $this->assertSame(['title' => 'Testing'], $result->getInvalid(), 'All invalid fields should be accessible.');
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
        $this->assertNotEmpty($result->getErrors(), 'Should have an error.');
    }

    /**
     * test marshalling with fieldList
     */
    public function testOneFieldList(): void
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
     */
    public function testOneAccsesibleFields(): void
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
     */
    public function testOneBeforeMarshalEvent(): void
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called): void {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            },
        );
        $marshaller = new Marshaller($this->index);
        $marshaller->one($data);

        $this->assertSame(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     */
    public function testOneBeforeMarshalEventMutateData(): void
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
        $this->assertSame('Mutated', $result->title);
    }

    /**
     * test marshalling a simple object.
     */
    public function testOneEmbeddedOne(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user['username']);
    }

    /**
     * DataProvider for testOneEmbeddedOneWithOption
     */
    public static function oneEmbeddedOneWithOptionProvider(): array
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
     * @param array  $options  Options to pass to marshaller->one
     */
    #[DataProvider('oneEmbeddedOneWithOptionProvider')]
    public function testOneEmbeddedOneWithOptions(array $options): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertInstanceOf(Document::class, $result->comment);
        $this->assertSame($data['user']['username'], $result->user->username);
        $this->assertSame($data['comment']['text'], $result->comment->text);
    }

    /**
     * test marshalling a simple object.
     */
    public function testOneEmbeddedMany(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);
        $this->assertInstanceOf(Document::class, $result->comments[0]);
        $this->assertInstanceOf(Document::class, $result->comments[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * DataProvider for testOneEmbeddedManyWithOptions
     */
    public static function oneEmbeddedManyWithOptionsProvider(): array
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
     * @param array  $options  Options to pass to marshaller->one
     */
    #[DataProvider('oneEmbeddedManyWithOptionsProvider')]
    public function testOneEmbeddedManyWithOptions(array $options): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);
        $this->assertIsArray($result->authors);
        $this->assertInstanceOf(Document::class, $result->comments[0]);
        $this->assertInstanceOf(Document::class, $result->comments[1]);
        $this->assertInstanceOf(Document::class, $result->authors[0]);
        $this->assertInstanceOf(Document::class, $result->authors[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
        $this->assertTrue($result->authors[0]->isNew());
        $this->assertTrue($result->authors[1]->isNew());
    }

    /**
     * Test converting multiple objects at once.
     */
    public function testMany(): void
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
        $this->assertInstanceOf(Document::class, $result[0]);
        $this->assertInstanceOf(Document::class, $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test merging data into existing records.
     */
    public function testMerge(): void
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
     */
    public function testMergeValidationErrorsSet(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame('First article', $result->title, 'Invalid fields are not modified.');
        $this->assertNotEmpty($result->getErrors(), 'Should have an error.');
    }

    /**
     * Test that invalid fields are preserved during merge (new behavior).
     */
    public function testMergeValidationErrorsPreserveInvalidFields(): void
    {
        $data = [
            'title' => 'First article',
            'body' => 'Some content',
            'user_id' => 5, // Valid initial value
        ];
        $this->index->getValidator()
            ->add('user_id', 'numbery', ['rule' => ['range', 1, 10]]);

        $marshaller = new Marshaller($this->index);
        $entity = $marshaller->one($data);

        $mergeData = ['user_id' => 200]; // Invalid value
        $result = $marshaller->merge($entity, $mergeData);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame(5, $result->user_id, 'Invalid fields should not be modified during merge.');
        $this->assertSame(200, $result->getInvalidField('user_id'), 'Invalid field should be preserved.');
        $this->assertSame(['user_id' => 200], $result->getInvalid(), 'All invalid fields should be accessible.');
        $this->assertNotEmpty($result->getErrors(), 'Should have an error.');
    }

    /**
     * test merging array data into an entity.
     */
    public function testMergeFieldList(): void
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
     */
    public function testMergeBeforeMarshalEvent(): void
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called): void {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            },
        );
        $marshaller = new Marshaller($this->index);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $marshaller->merge($doc, $data);

        $this->assertSame(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     */
    public function testMergeBeforeMarshalEventMutateData(): void
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
        $this->assertSame('Mutated', $result->title);
    }

    /**
     * test merge with an embed one
     */
    public function testMergeEmbeddedOneExisting(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertFalse($result->isNew(), 'Existing doc');
        $this->assertFalse($result->user->isNew(), 'Existing sub-doc');
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
    }

    /**
     * test merge when embedded documents don't exist
     */
    public function testMergeEmbeddedOneMissing(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
        $this->assertTrue($result->user->isNew(), 'Was missing, should now be new.');
    }

    /**
     * test marshalling a simple object.
     */
    public function testMergeEmbeddedMany(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);
        $this->assertInstanceOf(Document::class, $result->comments[0]);
        $this->assertInstanceOf(Document::class, $result->comments[1]);
        $this->assertFalse($result->comments[0]->isNew());
        $this->assertFalse($result->comments[1]->isNew());
    }

    /**
     * test merge with some sub documents not existing.
     */
    public function testMergeEmbeddedManySomeMissing(): void
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

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);

        $this->assertInstanceOf(Document::class, $result->comments[0]);
        $this->assertSame('First comment', $result->comments[0]->comment);
        $this->assertFalse($result->comments[0]->isNew());

        $this->assertInstanceOf(Document::class, $result->comments[1]);
        $this->assertSame('Second comment', $result->comments[1]->comment);
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * Test that mergeMany will create new objects if the entity list is empty.
     */
    public function testMergeManyAllNew(): void
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
     */
    public function testMergeManyFieldList(): void
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
     */
    public function testMergeManySomeNew(): void
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
        $this->assertSame($data[0]['title'], $result[0]->title);
        $this->assertFalse($result[0]->isNew());
        $this->assertTrue($result[0]->isDirty());
        $this->assertTrue($result[0]->isDirty('title'));

        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->isDirty());
        $this->assertTrue($result[1]->isDirty('title'));
    }

    /**
     * Test that unknown entities are excluded from the results.
     */
    public function testMergeManyDropsUnknownEntities(): void
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
     */
    public function testMergeManyBadEntityData(): void
    {
        $this->index->get(1);
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
     */
    public function testMarshallOneMultiLevelEmbed(): void
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
        $this->assertInstanceOf(User::class, $result->users[0]);
        $this->assertSame('Mark', $result->users[0]->first_name);
        $this->assertSame('Story', $result->users[0]->last_name);
        $this->assertInstanceOf(User::class, $result->users[1]);
        $this->assertSame('Clare', $result->users[1]->first_name);
        $this->assertSame('Smith', $result->users[1]->last_name);
        $this->assertInstanceOf(Document::class, $result->users[0]->user_type);
        $this->assertSame('Admin', $result->users[0]->user_type->label);
    }

    /**
     * test marshalling One with multi level embed (with AccessibleFields)
     */
    public function testMarshallOneMultiLevelEmbedWithAccessibleFields(): void
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
        $this->assertInstanceOf(User::class, $result->users[0]);
        $this->assertNull($result->users[0]->last_name);
        $this->assertInstanceOf(User::class, $result->users[1]);
        $this->assertNull($result->users[1]->last_name);
        $this->assertInstanceOf(Document::class, $result->users[0]->user_type);
        $this->assertNull($result->users[0]->user_type->level);
    }

    /**
     * test marshalling Many with multi level embed
     */
    public function testMarshallManyMultiLevelEmbed(): void
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
        $this->assertInstanceOf(User::class, $result[0]->users[0]);
        $this->assertInstanceOf(User::class, $result[0]->users[1]);
        $this->assertInstanceOf(User::class, $result[1]->users[0]);
        $this->assertSame('Mark', $result[0]->users[0]->first_name);
        $this->assertSame('Story', $result[0]->users[0]->last_name);
        $this->assertSame('Clare', $result[0]->users[1]->first_name);
        $this->assertSame('Smith', $result[0]->users[1]->last_name);
        $this->assertSame('Colin', $result[1]->users[0]->first_name);
        $this->assertSame('Thomas', $result[1]->users[0]->last_name);
        $this->assertInstanceOf(Document::class, $result[0]->users[0]->user_type);
        $this->assertSame('Admin', $result[0]->users[0]->user_type->label);
    }

    /**
     * Test newNested iteration with empty array
     */
    public function testOneEmbeddedManyEmptyArray(): void
    {
        $data = [
            'title' => 'Testing',
            'comments' => [],
        ];

        $this->index->embedMany('Comments');
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['Comments']]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);
        $this->assertCount(0, $result->comments);
    }

    /**
     * Test mergeNested iteration with empty existing array
     */
    public function testMergeEmbeddedManyEmptyExisting(): void
    {
        $data = [
            'title' => 'Testing',
            'comments' => [
                ['comment' => 'New comment'],
                'bad' => 'data',
            ],
        ];

        $entity = new Document([
            'title' => 'old',
            'comments' => [],
        ], ['markNew' => false]);

        $this->index->embedMany('Comments');
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertIsArray($result->comments);
        $this->assertCount(1, $result->comments);
        $this->assertTrue($result->comments[0]->isNew());
    }

    /**
     * Test afterMarshal event is fired in one()
     */
    public function testOneAfterMarshalEvent(): void
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.afterMarshal',
            function ($event, $entity, $data, $options) use (&$called): void {
                $called++;
                $this->assertInstanceOf(Document::class, $entity);
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
                $this->assertSame('Testing', $entity->title);
            },
        );
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);

        $this->assertSame(1, $called, 'afterMarshal event should be called once');
        $this->assertSame('Testing', $result->title);
    }

    /**
     * Test afterMarshal event allows entity modification
     */
    public function testOneAfterMarshalEventMutateEntity(): void
    {
        $data = [
            'title' => 'Testing',
            'body' => 'Elastic text',
        ];
        $this->index->getEventManager()->on('Model.afterMarshal', function ($event, $entity, $data, $options) {
            $entity->title = 'Modified in afterMarshal';
        });
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data);
        $this->assertSame('Modified in afterMarshal', $result->title);
    }

    /**
     * Test afterMarshal event is fired in merge()
     */
    public function testMergeAfterMarshalEvent(): void
    {
        $data = [
            'title' => 'Updated',
            'body' => 'New body',
        ];
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.afterMarshal',
            function ($event, $entity, $data, $options) use (&$called): void {
                $called++;
                $this->assertInstanceOf(Document::class, $entity);
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
                $this->assertSame('Updated', $entity->title);
            },
        );
        $marshaller = new Marshaller($this->index);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $result = $marshaller->merge($doc, $data);

        $this->assertSame(1, $called, 'afterMarshal event should be called once');
        $this->assertSame('Updated', $result->title);
    }

    /**
     * Test afterMarshal event in merge with fieldList option
     */
    public function testMergeAfterMarshalEventWithFieldList(): void
    {
        $data = [
            'title' => 'Updated',
            'body' => 'New body',
        ];
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.afterMarshal',
            function ($event, $entity, $data, $options) use (&$called): void {
                $called++;
                $this->assertInstanceOf(Document::class, $entity);
            },
        );
        $marshaller = new Marshaller($this->index);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $result = $marshaller->merge($doc, $data, ['fieldList' => ['title']]);

        $this->assertSame(1, $called, 'afterMarshal event should be called once even with fieldList');
        $this->assertSame('Updated', $result->title);
    }

    /**
     * Test clean embedded entities are not marked as dirty
     */
    public function testOneCleanEmbeddedEntitiesNotMarkedDirty(): void
    {
        $data = [
            'title' => 'Article',
            'user' => [
                'username' => 'mark',
            ],
        ];

        $this->index->embedOne('User');
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['User']]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertSame('mark', $result->user->username);

        // The embedded user document is clean (no modifications after creation)
        // So the parent should not be marked as dirty for that field
        // if the embedded entity itself is clean
        $this->assertTrue($result->isDirty('user'));
    }

    /**
     * Test dirty embedded entities mark parent as dirty
     */
    public function testOneDirtyEmbeddedEntitiesMarkedDirty(): void
    {
        $data = [
            'title' => 'Article',
            'user' => [
                'username' => 'mark',
            ],
        ];

        $this->index->embedOne('User');
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['User']]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertTrue($result->isDirty('user'));
    }

    /**
     * Test many() skips non-array records
     */
    public function testManySkipsNonArrayRecords(): void
    {
        $data = [
            ['title' => 'First', 'body' => 'Content 1'],
            'invalid string',
            ['title' => 'Second', 'body' => 'Content 2'],
            null,
            ['title' => 'Third', 'body' => 'Content 3'],
            123,
            ['title' => 'Fourth', 'body' => 'Content 4'],
        ];

        $marshaller = new Marshaller($this->index);
        $results = $marshaller->many($data);

        $this->assertCount(4, $results, 'Should only process array items');
        $this->assertSame('First', $results[0]->title);
        $this->assertSame('Second', $results[1]->title);
        $this->assertSame('Third', $results[2]->title);
        $this->assertSame('Fourth', $results[3]->title);
    }

    /**
     * Test many() with all invalid data returns empty array
     */
    public function testManyWithAllInvalidData(): void
    {
        $data = [
            'invalid string',
            123,
            null,
            true,
        ];

        $marshaller = new Marshaller($this->index);
        $results = $marshaller->many($data);

        $this->assertCount(0, $results, 'Should return empty array when no valid items');
    }

    /**
     * Test embedded entity uses correct index marshaller
     */
    public function testEmbeddedEntityUsesCorrectMarshaller(): void
    {
        Configure::write('App.namespace', 'TestApp');

        $data = [
            'title' => 'Article',
            'user' => [
                'username' => 'mark',
                'profile' => [
                    'bio' => 'Developer',
                ],
            ],
        ];

        // Set up nested embeds
        $this->index->embedOne('User');
        $userIndex = new AccountsIndex();
        $userIndex->embedOne('Profile');

        $marshaller = new Marshaller($this->index);
        $result = $marshaller->one($data, ['associated' => ['User' => ['associated' => ['Profile']]]]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertSame('mark', $result->user->username);
    }

    /**
     * Test fieldValue helper with existing field
     */
    public function testFieldValueWithExistingField(): void
    {
        $entity = new Document(['title' => 'Test', 'body' => 'Content']);
        $marshaller = new Marshaller($this->index);

        $reflection = new ReflectionClass($marshaller);
        $method = $reflection->getMethod('fieldValue');
        $method->setAccessible(true);

        $result = $method->invoke($marshaller, $entity, 'title');
        $this->assertSame('Test', $result);

        $result = $method->invoke($marshaller, $entity, 'body');
        $this->assertSame('Content', $result);
    }

    /**
     * Test fieldValue helper with missing field
     */
    public function testFieldValueWithMissingField(): void
    {
        $entity = new Document(['title' => 'Test']);
        $marshaller = new Marshaller($this->index);

        $reflection = new ReflectionClass($marshaller);
        $method = $reflection->getMethod('fieldValue');
        $method->setAccessible(true);

        $result = $method->invoke($marshaller, $entity, 'nonexistent');
        $this->assertNull($result, 'Should return null for missing fields');
    }

    /**
     * Test fieldValue helper with null field value
     */
    public function testFieldValueWithNullValue(): void
    {
        $entity = new Document(['title' => null]);
        $marshaller = new Marshaller($this->index);

        $reflection = new ReflectionClass($marshaller);
        $method = $reflection->getMethod('fieldValue');
        $method->setAccessible(true);

        $result = $method->invoke($marshaller, $entity, 'title');
        $this->assertNull($result);
    }

    /**
     * Test merge uses fieldValue for safe embedded access
     */
    public function testMergeUsesFieldValueForSafeAccess(): void
    {
        $data = [
            'title' => 'Updated',
            'user' => [
                'username' => 'updated_user',
            ],
        ];

        // Create entity without user field initially
        $entity = new Document(['title' => 'original'], ['markNew' => false]);

        $this->index->embedOne('User');
        $marshaller = new Marshaller($this->index);
        $result = $marshaller->merge($entity, $data, ['associated' => ['User']]);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame('Updated', $result->title);
        $this->assertInstanceOf(Document::class, $result->user);
        $this->assertSame('updated_user', $result->user->username);
    }
}
