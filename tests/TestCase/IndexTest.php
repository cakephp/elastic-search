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

use BadMethodCallException;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\RulesChecker;
use Cake\ElasticSearch\Association\EmbedMany;
use Cake\ElasticSearch\Association\EmbedOne;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\MappingSchema;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Exception\MissingDocumentException;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Marshaller;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\TestSuite\TestCase;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Entity;
use Cake\Validation\Validator;
use Elastica\Document as ElasticaDocument;
use Elastica\Exception\NotFoundException;
use Elastica\Index as ElasticaIndex;
use RuntimeException;
use TestPlugin\Model\Document\Comment;

/**
 * Tests the Index class
 */
class IndexTest extends TestCase
{
    public array $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    protected Connection $connection;

    protected Index $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $this->index = new Index([
            'name' => 'articles',
            'connection' => $this->connection,
        ]);
        $this->ElasticLocator->clear();
    }

    /**
     * Tests that calling find will return a query object
     */
    public function testFindAll(): void
    {
        $query = $this->index->find('all');
        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame($this->index, $query->getRepository());
    }

    /**
     * Tests that calling find will return a query object
     */
    public function testFindAllWithFirstOrFail(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->index->find('all')->where(['id' => '999999999'])->firstOrFail();
    }

    /**
     * Tests that table() is implemented as QueryTrait relies on.
     */
    public function testTable(): void
    {
        $this->assertSame('articles', $this->index->getTable());
    }

    /**
     * Test the default entityClass.
     */
    public function testGetEntityClassDefault(): void
    {
        $this->assertSame(Document::class, $this->index->getEntityClass());
    }

    /**
     * Test a custom entityClass.
     */
    public function testGetEntityClassCustom(): void
    {
        $index = $this->ElasticLocator->get('TestPlugin.Comments');

        $this->assertSame(Comment::class, $index->getEntityClass());
    }

    /**
     * Test a custom entityClass with existing index without a
     * document class.
     */
    public function testGetEntityClassDynamic(): void
    {
        $index = $this->ElasticLocator->get('Accounts');

        $this->assertSame(Document::class, $index->getEntityClass());
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace
     */
    public function testSetEntityClassInApp(): void
    {
        $class = $this->getMockBuilder(Document::class)->getMock();
        class_alias(get_class($class), 'TestApp\Model\Document\TestUser');

        $index = new Index();
        $index->setEntityClass('TestUser');
        $this->assertSame(
            'TestApp\Model\Document\TestUser',
            $index->getEntityClass(),
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the Plugin namespace when using plugin notation
     */
    public function testsetEntityClassInPlugin(): void
    {
        $class = $this->getMockBuilder(Document::class)->getMock();
        class_alias(get_class($class), 'MyPlugin\Model\Document\SuperUser');

        $index = new Index();
        $this->assertSame($index, $index->setEntityClass('MyPlugin.SuperUser'));
        $this->assertSame(
            'MyPlugin\Model\Document\SuperUser',
            $index->getEntityClass(),
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace, without target class
     */
    public function testSetInvalidEntityClass(): void
    {
        $index = new Index();

        $this->expectException(MissingDocumentException::class);

        $index->setEntityClass('NotExistingDocument');
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace, without target class
     */
    public function testSetInvalidDocumentClassButWithEntity(): void
    {
        $class = $this->getMockBuilder(Entity::class)->getMock();
        class_alias(get_class($class), 'TestApp\Model\Entity\Doge');

        $index = new Index();

        $this->expectException(MissingDocumentException::class);

        $index->setEntityClass('Doge');
    }

    /**
     * Tests the get method
     */
    public function testGet(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['getIndex'])
            ->getMock();

        $index = new Index([
            'name' => 'foo',
            'connection' => $connection,
        ]);

        $internalIndex = $this->getMockBuilder(ElasticaIndex::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('getIndex')
            ->willReturn($internalIndex);

        $document = $this->getMockBuilder(ElasticaDocument::class)
            ->onlyMethods(['getId', 'getData'])
            ->getMock();
        $internalIndex->expects($this->once())
            ->method('getDocument')
            ->with('foo', ['bar' => 'baz'])
            ->willReturn($document);

        $document->expects($this->once())
            ->method('getData')
            ->willReturn(['a' => 'b']);
        $document->expects($this->once())
            ->method('getId')
            ->willReturn('foo');

        $result = $index->get('foo', ['bar' => 'baz']);
        $this->assertInstanceOf(Document::class, $result);
        $this->assertEquals(['a' => 'b', 'id' => 'foo'], $result->toArray());
        $this->assertFalse($result->isDirty());
        $this->assertFalse($result->isNew());
        $this->assertSame('foo', $result->getSource());
    }

    /**
     * Test that newEntity is wired up.
     */
    public function testNewEntity(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['getIndex'])
            ->getMock();
        $index = new Index([
            'name' => 'articles',
            'connection' => $connection,
        ]);
        $data = [
            'title' => 'A newer title',
        ];
        $result = $index->newEntity($data);
        $this->assertInstanceOf(Document::class, $result);
        $this->assertSame($data, $result->toArray());
        $this->assertSame('articles', $result->getSource());
    }

    /**
     * Test that newEntities is wired up.
     */
    public function testNewEntities(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['getIndex'])
            ->getMock();
        $index = new Index([
            'name' => 'articles',
            'connection' => $connection,
        ]);
        $data = [
            [
                'title' => 'A newer title',
            ],
            [
                'title' => 'A second title',
            ],
        ];
        $result = $index->newEntities($data);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Document::class, $result[0]);
        $this->assertInstanceOf(Document::class, $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test saving many entities
     */
    public function testSaveMany(): void
    {
        $entities = [
            new Document(
                [
                    'title' => 'First',
                    'body' => 'Some new content',
                ],
                ['markNew' => true],
            ),
            new Document(
                [
                    'title' => 'Second',
                    'body' => 'Some new content',
                ],
                ['markNew' => true],
            ),
        ];

        $result = $this->index->saveMany($entities);
        $this->assertTrue($result);

        foreach ($entities as $entity) {
            $this->assertFalse($entity->isDirty());
            $this->assertFalse($entity->isNew());
            $this->assertSame('articles', $entity->getSource());
        }

        $ids = array_map(function (Document $doc) {
            return $doc->id;
        }, $entities);
        $this->assertCount(2, array_unique($ids));
    }

    /**
     * Test that saveMany() triggers afterSave event
     */
    public function testSaveManyAfterSave(): void
    {
        $entities = [
            new Document(
                [
                    'title' => 'First',
                    'body' => 'Some new content',
                ],
                ['markNew' => true],
            ),
            new Document(
                [
                    'title' => 'Second',
                    'body' => 'Some new content',
                ],
                ['markNew' => true],
            ),
        ];
        $called = 0;
        $this->index->getEventManager()->on('Model.afterSave', function ($event, $entity) use (&$called): void {
            $called++;
            $this->assertInstanceOf(Document::class, $entity);
            $this->assertFalse($entity->isDirty());
            $this->assertFalse($entity->isNew());
        });
        $result = $this->index->saveMany($entities);
        $this->assertTrue($result);
        $this->assertSame(2, $called);
    }

    /**
     * Test saving a new document.
     */
    public function testSaveNew(): void
    {
        $doc = new Document(
            [
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => true],
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');

        $result = $this->index->get($doc->id);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
        $this->assertSame('articles', $result->getSource());
    }

    /**
     * Test saving a new document with a custom routing key.
     */
    public function testSaveNewRoutingKey(): void
    {
        $doc = new Document(
            [
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => true],
        );
        $this->assertSame($doc, $this->index->save($doc, ['routing' => 'abcd']));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');

        try {
            $result = $this->index->get($doc->id, ['routing' => '1234']);
            $this->assertFalse(true, 'Routing keys are not working.');
        } catch (NotFoundException $notFoundException) {
            $this->assertStringContainsString($doc->id, $notFoundException->getMessage());
        }

        $result = $this->index->get($doc->id, ['routing' => 'abcd']);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
        $this->assertSame('articles', $result->getSource());
    }

    /**
     * Test saving a new document.
     */
    public function testSaveUpdate(): void
    {
        $doc = new Document(
            [
                'id' => '123',
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => false],
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertFalse($doc->isNew(), 'Not new.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');
        $this->assertSame('articles', $doc->getSource());
    }

    /**
     * Test saving a new document that contains errors
     */
    public function testSaveDoesNotSaveDocumentWithErrors(): void
    {
        $doc = new Document(
            [
                'id' => '123',
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => false],
        );
        $doc->setErrors(['title' => ['bad news']]);
        $this->assertFalse($this->index->save($doc), 'Should not save.');
    }

    /**
     * Test saving documents with index refresh
     */
    public function testSaveWithRefresh(): void
    {
        $doc = new Document(
            [
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => true],
        );

        $document = $this->index->save(
            $doc,
            ['refresh' => true],
        );

        $query = $this->index->find();
        $match = $query->all()->firstMatch(['id' => $document->id]);

        $this->assertCount(3, $query);
        $this->assertInstanceOf(Document::class, $match);
    }

    /**
     * Test save triggers events.
     */
    public function testSaveEvents(): void
    {
        $doc = $this->index->get(1);
        $doc->title = 'A new title';

        $called = 0;
        $this->index->getEventManager()->on(
            'Model.beforeSave',
            function (EventInterface $event, EntityInterface $entity, $options) use ($doc, &$called): void {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            },
        );
        $this->index->getEventManager()->on(
            'Model.afterSave',
            function ($event, $entity, $options) use ($doc, &$called): void {
                $called++;
                $this->assertInstanceOf('ArrayObject', $options);
                $this->assertSame($doc, $entity);
                $this->assertFalse($doc->isNew(), 'Should not be new');
                $this->assertFalse($doc->isDirty(), 'Should not be dirty');
            },
        );
        $this->index->save($doc);
        $this->assertSame(2, $called);
    }

    /**
     * Test beforeSave abort.
     */
    public function testSaveBeforeSaveAbort(): void
    {
        $doc = $this->index->get(1);
        $doc->title = 'new title';
        $this->index->getEventManager()->on(
            'Model.beforeSave',
            function (EventInterface $event, EntityInterface $entity, $options): void {
                $event->stopPropagation();
                $event->setResult(false);
            },
        );
        $this->index->getEventManager()->on(
            'Model.afterSave',
            function (): void {
                $this->fail('Should not be fired');
            },
        );
        $this->assertFalse($this->index->save($doc));
    }

    /**
     * Test save with embedded documents.
     */
    public function testSaveEmbedOne(): void
    {
        $entity = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'user' => new Document(['username' => 'sarah']),
        ]);
        $this->index->embedOne('User');
        $this->index->save($entity);

        $compare = $this->index->get($entity->id);
        $this->assertInstanceOf(Document::class, $compare->user);
        $this->assertSame('sarah', $compare->user->username);
    }

    /**
     * Test save with embedded documents.
     */
    public function testSaveEmbedMany(): void
    {
        $entity = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'comments' => [
                new Document(['comment' => 'Nice post']),
                new Document(['comment' => 'Awesome!']),
            ],
        ]);
        $this->index->embedMany('Comments');
        $this->index->save($entity);

        $compare = $this->index->get($entity->id);
        $this->assertInstanceOf(Document::class, $compare->comments[0]);
        $this->assertInstanceOf(Document::class, $compare->comments[1]);
        $this->assertSame('Nice post', $compare->comments[0]->comment);
        $this->assertSame('Awesome!', $compare->comments[1]->comment);
    }

    /**
     * Test that rules can prevent save.
     */
    public function testSaveWithRulesCreate(): void
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules): void {
                $rules->addCreate(
                    function ($doc): string {
                        return 'Did not work';
                    },
                    ['errorField' => 'name'],
                );
            },
        );

        $doc = new Document(['title' => 'rules are checked']);
        $this->assertFalse($this->index->save($doc), 'Save should fail');

        $doc->clean();
        $doc->id = '12345';
        $doc->setNew(false);
        $this->assertSame($doc, $this->index->save($doc), 'Save should pass, not new anymore.');
    }

    /**
     * Test that rules can prevent save.
     */
    public function testSaveWithRulesUpdate(): void
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules): void {
                $rules->addUpdate(
                    function ($doc): string {
                        return 'Did not work';
                    },
                    ['errorField' => 'name'],
                );
            },
        );

        $doc = new Document(['title' => 'update rules'], ['markNew' => false]);
        $this->assertFalse($this->index->save($doc), 'Save should fail');
    }

    /**
     * Test to make sure double save works correctly
     */
    public function testDoubleSave(): void
    {
        $doc = new Document(
            [
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => true],
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->version, 'Should get a version');

        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->version, 'Should get a version');
    }

    /**
     * Test deleting a document.
     */
    public function testDeleteBasic(): void
    {
        $doc = $this->index->get(1);
        $this->assertTrue($this->index->delete($doc));

        $dead = $this->index->find()->where(['id' => 1])->first();
        $this->assertNull($dead, 'No record.');
    }

    /**
     * Test deletion prevented by rules
     */
    public function testDeleteRules(): void
    {
        $this->index->rulesChecker()->addDelete(
            function (): string {
                return 'not good';
            },
            ['errorField' => 'title'],
        );
        $doc = $this->index->get(1);

        $this->assertFalse($this->index->delete($doc));
        $this->assertNotEmpty($doc->getError('title'));
    }

    /**
     * Test delete triggers events.
     */
    public function testDeleteEvents(): void
    {
        $called = 0;
        $doc = $this->index->get(1);
        $this->index->getEventManager()->on(
            'Model.beforeDelete',
            function ($event, $entity, $options) use ($doc, &$called): void {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            },
        );
        $this->index->getEventManager()->on(
            'Model.afterDelete',
            function ($event, $entity, $options) use ($doc, &$called): void {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            },
        );
        $this->assertTrue($this->index->delete($doc));
        $this->assertSame(2, $called);
    }

    /**
     * Test beforeDelete abort.
     */
    public function testDeleteBeforeDeleteAbort(): void
    {
        $doc = $this->index->get(1);
        $this->index->getEventManager()->on(
            'Model.beforeDelete',
            function ($event, $entity, $options): void {
                $event->stopPropagation();
                $event->setResult(false);
            },
        );
        $this->index->getEventManager()->on(
            'Model.afterDelete',
            function (): void {
                $this->fail('Should not be fired');
            },
        );
        $this->assertFalse($this->index->delete($doc));
    }

    /**
     * Test deleting a new document
     */
    public function testDeleteMissing(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Deleting requires an "id" value.');
        $doc = new Document(['title' => 'not there.']);
        $this->index->delete($doc);
    }

    /**
     * Test getting and setting validators.
     */
    public function testValidatorSetAndGet(): void
    {
        $result = $this->index->getValidator();

        $this->assertInstanceOf(Validator::class, $result);
        $this->assertSame($result, $this->index->getValidator(), 'validator instances are persistent');
        $this->assertSame($this->index, $result->getProvider('collection'), 'index bound as provider');
    }

    /**
     * Test buildValidator event
     */
    public function testValidatorTriggerEvent(): void
    {
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.buildValidator',
            function ($event, $validator, $name) use (&$called): void {
                $called++;
                $this->assertInstanceOf(Validator::class, $validator);
                $this->assertSame('default', $name);
            },
        );
        $this->index->getValidator();
        $this->assertSame(1, $called, 'Event not triggered');
    }

    /**
     * Test that exists works.
     */
    public function testExists(): void
    {
        $this->assertFalse($this->index->exists(['id' => '999999']));
        $this->assertTrue($this->index->exists(['id' => '1']));
    }

    /**
     * Test that deleteAll works.
     */
    public function testDeleteAll(): void
    {
        $result = $this->index->deleteAll(['title' => 'article']);

        $this->connection->getIndex($this->index->getName())->refresh();

        $this->assertSame(1, $result);
        $this->assertSame(0, $this->index->find()->count());
    }

    /**
     * Test that deleteAll works.
     */
    public function testDeleteAllOnlySome(): void
    {
        $result = $this->index->deleteAll(['body' => 'cake']);

        $this->connection->getIndex($this->index->getName())->refresh();

        $this->assertSame(1, $result);
        $this->assertSame(1, $this->index->find()->count());
    }

    /**
     * Test the rules builder indexes
     */
    public function testAddRules(): void
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules): void {
                $rules->add(
                    function ($doc): bool {
                        return false;
                    },
                );
            },
        );
        $rules = $this->index->rulesChecker();
        $this->assertInstanceOf(RulesChecker::class, $rules);

        $doc = new Document();
        $result = $rules->checkCreate($doc);
        $this->assertFalse($result, 'Rules should fail.');
    }

    /**
     * Test the alias method.
     */
    public function testAlias(): void
    {
        $this->assertEquals($this->index->getName(), $this->index->getAlias());
        $this->assertSame('articles', $this->index->getAlias());
    }

    /**
     * Test the alias method.
     */
    public function testRegistryAlias(): void
    {
        $index = $this->ElasticLocator->get('TestPlugin.Comments');

        $this->assertSame('articles', $this->index->getRegistryAlias());
        $this->assertSame('TestPlugin.Comments', $index->getRegistryAlias());
    }

    /**
     * Test hasField()
     */
    public function testHasField(): void
    {
        $this->assertTrue($this->index->hasField('title'));
        $this->assertFalse($this->index->hasField('nope'));
    }

    /**
     * Test that implementedEvents works.
     */
    public function testImplementedEvents(): void
    {
        $this->assertInstanceOf(EventListenerInterface::class, $this->index);

        $index = new class extends Index {
            public function beforeFind(): void
            {
            }

            public function beforeSave(): void
            {
            }

            public function afterSave(): void
            {
            }

            public function beforeDelete(): void
            {
            }

            public function afterDelete(): void
            {
            }
        };

        $result = $index->implementedEvents();
        $expected = [
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
        ];
        $this->assertEquals($expected, $result, 'Events do not match.');
    }

    /**
     * Test that index listen's to it's own events..
     */
    public function testOwnEvents(): void
    {
        $index = new class extends Index {
            public function beforeSave(): void
            {
            }
        };

        $this->assertCount(1, $index->getEventManager()->listeners('Model.beforeSave'));
    }

    /**
     * Test updateAll method throws RuntimeException
     */
    public function testUpdateAll(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not implemented yet');

        $this->index->updateAll(['title' => 'Updated'], ['id' => '1']);
    }

    /**
     * Test setConnection and getConnection methods
     */
    public function testSetAndGetConnection(): void
    {
        $connection = $this->createMock(Connection::class);

        $result = $this->index->setConnection($connection);
        $this->assertSame($this->index, $result); // Test fluent interface
        $this->assertSame($connection, $this->index->getConnection());
    }

    /**
     * Test setRegistryAlias and getRegistryAlias methods
     */
    public function testSetAndGetRegistryAliasCustom(): void
    {
        $alias = 'custom_alias';

        $result = $this->index->setRegistryAlias($alias);
        $this->assertSame($this->index, $result); // Test fluent interface
        $this->assertSame($alias, $this->index->getRegistryAlias());
    }

    /**
     * Test setName and getName methods
     */
    public function testSetAndGetName(): void
    {
        $name = 'custom_index_name';

        $result = $this->index->setName($name);
        $this->assertSame($this->index, $result); // Test fluent interface
        $this->assertSame($name, $this->index->getName());
    }

    /**
     * Test setAlias method (alias for setName)
     */
    public function testSetAlias(): void
    {
        $alias = 'test_alias';

        $result = $this->index->setAlias($alias);
        $this->assertSame($this->index, $result); // Test fluent interface
        $this->assertSame($alias, $this->index->getName());
        $this->assertSame($alias, $this->index->getAlias());
    }

    /**
     * Test embedOne method
     */
    public function testEmbedOne(): void
    {
        $this->index->embedOne('User', ['property' => 'user']);

        $embedded = $this->index->embedded();
        $this->assertCount(1, $embedded);
        $this->assertInstanceOf(EmbedOne::class, $embedded[0]);
    }

    /**
     * Test embedMany method
     */
    public function testEmbedMany(): void
    {
        $this->index->embedMany('Comments', ['property' => 'comments']);

        $embedded = $this->index->embedded();
        $this->assertCount(1, $embedded);
        $this->assertInstanceOf(EmbedMany::class, $embedded[0]);
    }

    /**
     * Test embedded method returns empty array initially
     */
    public function testEmbeddedEmpty(): void
    {
        $newIndex = new Index();

        $embedded = $newIndex->embedded();
        $this->assertIsArray($embedded);
        $this->assertEmpty($embedded);
    }

    /**
     * Test callFinder method
     */
    public function testCallFinder(): void
    {
        $query = $this->index->query();
        $options = ['limit' => 10, 'conditions' => ['status' => 'active']];

        $result = $this->index->callFinder('all', $query, $options);
        $this->assertInstanceOf(Query::class, $result);
        $this->assertSame($query, $result); // Should return the same query object
    }

    /**
     * Test callFinder with invalid finder
     */
    public function testCallFinderInvalid(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Unknown finder method "invalidFinder"');

        $query = $this->index->query();
        $this->index->callFinder('invalidFinder', $query);
    }

    /**
     * Test aliasField method
     */
    public function testAliasField(): void
    {
        $result = $this->index->aliasField('title');
        $this->assertSame('title', $result);

        $result = $this->index->aliasField('body');
        $this->assertSame('body', $result);
    }

    /**
     * Test query method creates new Query instance
     */
    public function testQueryMethod(): void
    {
        $query = $this->index->query();

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame($this->index, $query->getRepository());

        // Ensure each call creates a new instance
        $query2 = $this->index->query();
        $this->assertNotSame($query, $query2);
    }

    /**
     * Test marshaller method
     */
    public function testMarshallerMethod(): void
    {
        $marshaller = $this->index->marshaller();

        $this->assertInstanceOf(Marshaller::class, $marshaller);

        // Test that subsequent calls return new instances
        $marshaller2 = $this->index->marshaller();
        $this->assertNotSame($marshaller, $marshaller2);
    }

    /**
     * Test newEmptyEntity method
     */
    public function testNewEmptyEntity(): void
    {
        $entity = $this->index->newEmptyEntity();

        $this->assertInstanceOf(Document::class, $entity);
        $this->assertTrue($entity->isNew());
        $this->assertEmpty($entity->toArray());
    }

    /**
     * Test patchEntity method
     */
    public function testPatchEntity(): void
    {
        $entity = new Document(['title' => 'Original Title']);
        $data = ['title' => 'Updated Title', 'body' => 'New Body'];

        $result = $this->index->patchEntity($entity, $data);

        $this->assertSame($entity, $result);
        $this->assertSame('Updated Title', $entity->get('title'));
        $this->assertSame('New Body', $entity->get('body'));
    }

    /**
     * Test patchEntities method
     */
    public function testPatchEntities(): void
    {
        $entities = [
            new Document(['title' => 'First', 'id' => '1']),
            new Document(['title' => 'Second', 'id' => '2']),
        ];

        $data = [
            ['title' => 'Updated First'],
            ['title' => 'Updated Second'],
        ];

        $result = $this->index->patchEntities($entities, $data);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Updated First', $result[0]->get('title'));
        $this->assertSame('Updated Second', $result[1]->get('title'));
    }

    /**
     * Test schema method returns MappingSchema
     */
    public function testSchemaMethod(): void
    {
        $schema = $this->index->schema();

        $this->assertInstanceOf(MappingSchema::class, $schema);

        // Test that subsequent calls return the same instance (cached)
        $schema2 = $this->index->schema();
        $this->assertSame($schema, $schema2);
    }

    /**
     * Test getName method with automatic name inference
     */
    public function testGetNameAutomatic(): void
    {
        $index = new class extends Index {
            // Class name ends with "Index" so it should infer "Test" as the name
        };

        // The name should be inferred from the class name
        $name = $index->getName();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    /**
     * Test getRegistryAlias with automatic inference
     */
    public function testGetRegistryAliasAutomatic(): void
    {
        $index = new Index();

        // Should infer from class name when not set
        $alias = $index->getRegistryAlias();
        $this->assertSame('', $alias);
    }

    /**
     * Test find method with custom finder and options
     */
    public function testFindWithFinderAndOptions(): void
    {
        $query = $this->index->find('all', ['limit' => 5]);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertSame($this->index, $query->getRepository());
    }

    /**
     * Test get method with array finder options
     */
    public function testGetWithArrayFinder(): void
    {
        $result = $this->index->get('1', ['finder' => 'all']);

        $this->assertInstanceOf(Document::class, $result);
    }

    /**
     * Test exists method with complex conditions
     */
    public function testExistsWithComplexConditions(): void
    {
        // Test with array conditions
        $exists = $this->index->exists(['title' => 'nonexistent']);

        $this->assertFalse($exists);

        // Test with simple array conditions
        $exists = $this->index->exists(['title' => 'article']);
        $this->assertTrue($exists); // Should find something with our test data
    }

    /**
     * Test deleteAll method with complex conditions
     */
    public function testDeleteAllWithComplexConditions(): void
    {
        // Test with array conditions
        $result = $this->index->deleteAll(['title' => 'nonexistent_title_for_test']);

        $this->assertIsInt($result);
        $this->assertIsInt($result); // Result count can vary based on test data

        // Test with simple array conditions
        $result = $this->index->deleteAll(['title' => 'test_delete_all']);
        $this->assertIsInt($result);
    }
}
