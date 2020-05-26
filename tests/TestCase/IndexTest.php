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

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\IndexRegistry;
use Cake\Event\EventInterface;
use Cake\TestSuite\TestCase;
use Elastica\Exception\NotFoundException;

/**
 * Tests the Index class
 */
class IndexTest extends TestCase
{
    public $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
        $this->index = new Index(
            [
            'name' => 'articles',
            'connection' => $this->connection,
            ]
        );
    }

    /**
     * Tests that calling find will return a query object
     *
     * @return void
     */
    public function testFindAll()
    {
        $query = $this->index->find('all');
        $this->assertInstanceOf('Cake\ElasticSearch\Query', $query);
        $this->assertSame($this->index, $query->getRepository());
    }

    /**
     * Tests that calling find will return a query object
     *
     * @return            void
     */
    public function testFindAllWithFirstOrFail()
    {
        $this->expectException('Cake\Datasource\Exception\RecordNotFoundException');
        $this->index->find('all')->where(['id' => '999999999'])->firstOrFail();
    }

    /**
     * Tests that table() is implemented as QueryTrait relies on.
     *
     * @return void
     */
    public function testTable()
    {
        $this->assertSame('articles', $this->index->getTable());
    }

    /**
     * Test the default entityClass.
     *
     * @return void
     */
    public function testGetEntityClassDefault()
    {
        $this->assertEquals(Document::class, $this->index->getEntityClass());
    }

    /**
     * Test a custom entityClass.
     *
     * @return void
     */
    public function testGetEntityClassCustom()
    {
        $index = IndexRegistry::get('TestPlugin.Comments');

        $this->assertEquals('TestPlugin\Model\Document\Comment', $index->getEntityClass());
    }

    /**
     * Test a custom entityClass with existing index without a
     * document class.
     *
     * @return void
     */
    public function testGetEntityClassDynamic()
    {
        $index = IndexRegistry::get('Accounts');

        $this->assertEquals(Document::class, $index->getEntityClass());
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace
     *
     * @return void
     */
    public function testSetEntityClassInApp()
    {
        $class = $this->getMockClass('Cake\ElasticSearch\Document');
        class_alias($class, 'TestApp\Model\Document\TestUser');

        $index = new Index();
        $index->setEntityClass('TestUser');
        $this->assertEquals(
            'TestApp\Model\Document\TestUser',
            $index->getEntityClass()
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the Plugin namespace when using plugin notation
     *
     * @return void
     */
    public function testsetEntityClassInPlugin()
    {
        $class = $this->getMockClass('\Cake\ElasticSearch\Document');
        class_alias($class, 'MyPlugin\Model\Document\SuperUser');

        $index = new Index();
        $this->assertSame($index, $index->setEntityClass('MyPlugin.SuperUser'));
        $this->assertEquals(
            'MyPlugin\Model\Document\SuperUser',
            $index->getEntityClass()
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace, without target class
     *
     * @return void
     */
    public function testSetInvalidEntityClass()
    {
        $index = new Index();

        $this->expectException('\Cake\ElasticSearch\Exception\MissingDocumentException');

        $index->setEntityClass('NotExistingDocument');
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace, without target class
     *
     * @return void
     */
    public function testSetInvalidDocumentClassButWithEntity()
    {
        $class = $this->getMockClass('\Cake\ORM\Entity');
        class_alias($class, 'TestApp\Model\Entity\Doge');

        $index = new Index();

        $this->expectException('\Cake\ElasticSearch\Exception\MissingDocumentException');

        $index->setEntityClass('Doge');
    }

    /**
     * Tests the get method
     *
     * @return void
     */
    public function testGet()
    {
        $connection = $this->getMockBuilder('Cake\ElasticSearch\Datasource\Connection')
            ->setMethods(['getIndex'])
            ->getMock();

        $index = new Index(
            [
            'name' => 'foo',
            'connection' => $connection,
            ]
        );

        $internalIndex = $this->getMockBuilder('Elastica\Index')
            ->disableOriginalConstructor()
            ->getMock();

        $internalType = $this->getMockBuilder('Elastica\Type')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('getIndex')
            ->will($this->returnValue($internalIndex));

        $internalIndex->expects($this->once())
            ->method('getType')
            ->will($this->returnValue($internalType));

        $document = $this->getMockBuilder('Elastica\Document')
            ->setMethods(['getId', 'getData'])
            ->getMock();
        $internalType->expects($this->once())
            ->method('getDocument')
            ->with('foo', ['bar' => 'baz'])
            ->will($this->returnValue($document));

        $document->expects($this->once())
            ->method('getData')
            ->will($this->returnValue(['a' => 'b']));
        $document->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('foo'));

        $result = $index->get('foo', ['bar' => 'baz']);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertEquals(['a' => 'b', 'id' => 'foo'], $result->toArray());
        $this->assertFalse($result->isDirty());
        $this->assertFalse($result->isNew());
        $this->assertEquals('foo', $result->getSource());
    }

    /**
     * Test that newEntity is wired up.
     *
     * @return void
     */
    public function testNewEntity()
    {
        $connection = $this->getMockBuilder('Cake\ElasticSearch\Datasource\Connection')
            ->setMethods(['getIndex'])
            ->getMock();
        $index = new Index(
            [
            'name' => 'articles',
            'connection' => $connection,
            ]
        );
        $data = [
            'title' => 'A newer title',
        ];
        $result = $index->newEntity($data);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame($data, $result->toArray());
        $this->assertEquals('articles', $result->getSource());
    }

    /**
     * Test that newEntities is wired up.
     *
     * @return void
     */
    public function testNewEntities()
    {
        $connection = $this->getMockBuilder('Cake\ElasticSearch\Datasource\Connection')
            ->setMethods(['getIndex'])
            ->getMock();
        $index = new Index(
            [
            'name' => 'articles',
            'connection' => $connection,
            ]
        );
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
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test saving many entities
     *
     * @return void
     */
    public function testSaveMany()
    {
        $entities = [
            new Document(
                [
                'title' => 'First',
                'body' => 'Some new content',
                ],
                [
                'markNew' => true,
                ]
            ),
            new Document(
                [
                'title' => 'Second',
                'body' => 'Some new content',
                ],
                [
                'markNew' => true,
                ]
            ),
        ];

        $result = $this->index->saveMany($entities);
        $this->assertTrue($result);
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveNew()
    {
        $doc = new Document(
            [
            'title' => 'A brand new article',
            'body' => 'Some new content',
            ],
            ['markNew' => true]
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');

        $result = $this->index->get($doc->id);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
        $this->assertEquals('articles', $result->getSource());
    }

    /**
     * Test saving a new document with a custom routing key.
     *
     * @return void
     */
    public function testSaveNewRoutingKey()
    {
        $doc = new Document(
            [
                'title' => 'A brand new article',
                'body' => 'Some new content',
            ],
            ['markNew' => true]
        );
        $this->assertSame($doc, $this->index->save($doc, ['routing' => 'abcd']));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');

        try {
            $result = $this->index->get($doc->id, ['routing' => '1234']);
            $this->assertFalse(true, 'Routing keys are not working.');
        } catch (NotFoundException $e) {
            $this->assertStringContainsString($doc->id, $e->getMessage());
        }

        $result = $this->index->get($doc->id, ['routing' => 'abcd']);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
        $this->assertEquals('articles', $result->getSource());
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveUpdate()
    {
        $doc = new Document(
            [
            'id' => '123',
            'title' => 'A brand new article',
            'body' => 'Some new content',
            ],
            ['markNew' => false]
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertFalse($doc->isNew(), 'Not new.');
        $this->assertFalse($doc->isDirty(), 'Not dirty anymore.');
        $this->assertEquals('articles', $doc->getSource());
    }

    /**
     * Test saving a new document that contains errors
     *
     * @return void
     */
    public function testSaveDoesNotSaveDocumentWithErrors()
    {
        $doc = new Document(
            [
            'id' => '123',
            'title' => 'A brand new article',
            'body' => 'Some new content',
            ],
            ['markNew' => false]
        );
        $doc->setErrors(['title' => ['bad news']]);
        $this->assertFalse($this->index->save($doc), 'Should not save.');
    }

    /**
     * Test saving documents with index refresh
     *
     * @return void
     */
    public function testSaveWithRefresh()
    {
        $doc = new Document(
            [
            'title' => 'A brand new article',
            'body' => 'Some new content',
            ],
            ['markNew' => true]
        );

        $document = $this->index->save(
            $doc,
            [
            'refresh' => true,
            ]
        );

        $query = $this->index->find();
        $match = $query->firstMatch([ 'id' => $document->id ]);

        $this->assertCount(3, $query);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $match);
    }

    /**
     * Test save triggers events.
     *
     * @return void
     */
    public function testSaveEvents()
    {
        $doc = $this->index->get(1);
        $doc->title = 'A new title';

        $called = 0;
        $this->index->getEventManager()->on(
            'Model.beforeSave',
            function (EventInterface $event, EntityInterface $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->index->getEventManager()->on(
            'Model.afterSave',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $options);
                $this->assertSame($doc, $entity);
                $this->assertFalse($doc->isNew(), 'Should not be new');
                $this->assertFalse($doc->isDirty(), 'Should not be dirty');
            }
        );
        $this->index->save($doc);
        $this->assertEquals(2, $called);
    }

    /**
     * Test beforeSave abort.
     *
     * @return void
     */
    public function testSaveBeforeSaveAbort()
    {
        $doc = $this->index->get(1);
        $doc->title = 'new title';
        $this->index->getEventManager()->on(
            'Model.beforeSave',
            function (EventInterface $event, EntityInterface $entity, $options) use ($doc) {
                $event->stopPropagation();

                return 'kaboom';
            }
        );
        $this->index->getEventManager()->on(
            'Model.afterSave',
            function () {
                $this->fail('Should not be fired');
            }
        );
        $this->assertSame('kaboom', $this->index->save($doc));
    }

    /**
     * Test save with embedded documents.
     *
     * @return void
     */
    public function testSaveEmbedOne()
    {
        $entity = new Document(
            [
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'user' => new Document(['username' => 'sarah']),
            ]
        );
        $this->index->embedOne('User');
        $this->index->save($entity);

        $compare = $this->index->get($entity->id);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $compare->user);
        $this->assertEquals('sarah', $compare->user->username);
    }

    /**
     * Test save with embedded documents.
     *
     * @return void
     */
    public function testSaveEmbedMany()
    {
        $entity = new Document(
            [
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'comments' => [
                new Document(['comment' => 'Nice post']),
                new Document(['comment' => 'Awesome!']),
            ],
            ]
        );
        $this->index->embedMany('Comments');
        $this->index->save($entity);

        $compare = $this->index->get($entity->id);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $compare->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $compare->comments[1]);
        $this->assertEquals('Nice post', $compare->comments[0]->comment);
        $this->assertEquals('Awesome!', $compare->comments[1]->comment);
    }

    /**
     * Test that rules can prevent save.
     *
     * @return void
     */
    public function testSaveWithRulesCreate()
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules) {
                $rules->addCreate(
                    function ($doc) {
                        return 'Did not work';
                    },
                    ['errorField' => 'name']
                );
            }
        );

        $doc = new Document(['title' => 'rules are checked']);
        $this->assertFalse($this->index->save($doc), 'Save should fail');

        $doc->clean();
        $doc->id = 12345;
        $doc->setNew(false);
        $this->assertSame($doc, $this->index->save($doc), 'Save should pass, not new anymore.');
    }

    /**
     * Test that rules can prevent save.
     *
     * @return void
     */
    public function testSaveWithRulesUpdate()
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules) {
                $rules->addUpdate(
                    function ($doc) {
                        return 'Did not work';
                    },
                    ['errorField' => 'name']
                );
            }
        );

        $doc = new Document(['title' => 'update rules'], ['markNew' => false]);
        $this->assertFalse($this->index->save($doc), 'Save should fail');
    }

    /**
     * Test to make sure double save works correctly
     *
     * @return void
     */
    public function testDoubleSave()
    {
        $doc = new Document(
            [
            'title' => 'A brand new article',
            'body' => 'Some new content',
            ],
            ['markNew' => true]
        );
        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');

        $this->assertSame($doc, $this->index->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');
    }

    /**
     * Test deleting a document.
     *
     * @return void
     */
    public function testDeleteBasic()
    {
        $doc = $this->index->get(1);
        $this->assertTrue($this->index->delete($doc));

        $dead = $this->index->find()->where(['id' => 1])->first();
        $this->assertNull($dead, 'No record.');
    }

    /**
     * Test deletion prevented by rules
     *
     * @return void
     */
    public function testDeleteRules()
    {
        $this->index->rulesChecker()->addDelete(
            function () {
                return 'not good';
            },
            ['errorField' => 'title']
        );
        $doc = $this->index->get(1);

        $this->assertFalse($this->index->delete($doc));
        $this->assertNotEmpty($doc->getError('title'));
    }

    /**
     * Test delete triggers events.
     *
     * @return void
     */
    public function testDeleteEvents()
    {
        $called = 0;
        $doc = $this->index->get(1);
        $this->index->getEventManager()->on(
            'Model.beforeDelete',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->index->getEventManager()->on(
            'Model.afterDelete',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->assertTrue($this->index->delete($doc));
        $this->assertEquals(2, $called);
    }

    /**
     * Test beforeDelete abort.
     *
     * @return void
     */
    public function testDeleteBeforeDeleteAbort()
    {
        $doc = $this->index->get(1);
        $this->index->getEventManager()->on(
            'Model.beforeDelete',
            function ($event, $entity, $options) use ($doc) {
                $event->stopPropagation();

                return false;
            }
        );
        $this->index->getEventManager()->on(
            'Model.afterDelete',
            function () {
                $this->fail('Should not be fired');
            }
        );
        $this->assertFalse($this->index->delete($doc));
    }

    /**
     * Test deleting a new document
     *
     * @return                   void
     */
    public function testDeleteMissing()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Deleting requires an "id" value.');
        $doc = new Document(['title' => 'not there.']);
        $this->index->delete($doc);
    }

    /**
     * Test getting and setting validators.
     *
     * @return void
     */
    public function testValidatorSetAndGet()
    {
        $result = $this->index->getValidator();

        $this->assertInstanceOf('Cake\Validation\Validator', $result);
        $this->assertSame($result, $this->index->getValidator(), 'validator instances are persistent');
        $this->assertSame($this->index, $result->getProvider('collection'), 'index bound as provider');
    }

    /**
     * Test buildValidator event
     *
     * @return void
     */
    public function testValidatorTriggerEvent()
    {
        $called = 0;
        $this->index->getEventManager()->on(
            'Model.buildValidator',
            function ($event, $validator, $name) use (&$called) {
                $called++;
                $this->assertInstanceOf('Cake\Validation\Validator', $validator);
                $this->assertEquals('default', $name);
            }
        );
        $this->index->getValidator();
        $this->assertEquals(1, $called, 'Event not triggered');
    }

    /**
     * Test that exists works.
     *
     * @return void
     */
    public function testExists()
    {
        $this->assertFalse($this->index->exists(['id' => '999999']));
        $this->assertTrue($this->index->exists(['id' => '1']));
    }

    /**
     * Test that deleteAll works.
     *
     * @return void
     */
    public function testDeleteAll()
    {
        $result = $this->index->deleteAll(['title' => 'article']);

        $this->connection->getIndex($this->index->getName())->refresh();

        $this->assertSame(1, $result);
        $this->assertEquals(0, $this->index->find()->count());
    }

    /**
     * Test that deleteAll works.
     *
     * @return void
     */
    public function testDeleteAllOnlySome()
    {
        $result = $this->index->deleteAll(['body' => 'cake']);

        $this->connection->getIndex($this->index->getName())->refresh();

        $this->assertSame(1, $result);
        $this->assertEquals(1, $this->index->find()->count());
    }

    /**
     * Test the rules builder indexes
     *
     * @return void
     */
    public function testAddRules()
    {
        $this->index->getEventManager()->on(
            'Model.buildRules',
            function ($event, $rules) {
                $rules->add(
                    function ($doc) {
                        return false;
                    }
                );
            }
        );
        $rules = $this->index->rulesChecker();
        $this->assertInstanceOf('Cake\Datasource\RulesChecker', $rules);

        $doc = new Document();
        $result = $rules->checkCreate($doc);
        $this->assertFalse($result, 'Rules should fail.');
    }

    /**
     * Test the alias method.
     *
     * @return void
     */
    public function testAlias()
    {
        $this->assertEquals($this->index->getName(), $this->index->getAlias());
        $this->assertEquals('articles', $this->index->getAlias());
    }

    /**
     * Test the alias method.
     *
     * @return void
     */
    public function testRegistryAlias()
    {
        $index = IndexRegistry::get('TestPlugin.Comments');

        $this->assertEquals('articles', $this->index->getRegistryAlias());
        $this->assertEquals('TestPlugin.Comments', $index->getRegistryAlias());
    }

    /**
     * Test hasField()
     *
     * @return void
     */
    public function testHasField()
    {
        $this->assertTrue($this->index->hasField('title'));
        $this->assertFalse($this->index->hasField('nope'));
    }

    /**
     * Test that Index implements the EventListenerInterface and some events.
     *
     * @return void
     */
    public function testImplementedEvents()
    {
        $this->assertInstanceOf('Cake\Event\EventListenerInterface', $this->index);

        $index = $this->getMockBuilder('Cake\ElasticSearch\Index')
            ->setMethods(['beforeFind', 'beforeSave', 'afterSave', 'beforeDelete', 'afterDelete'])
            ->getMock();
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
     *
     * @return void
     */
    public function testOwnEvents()
    {
        $index = $this->getMockBuilder('Cake\ElasticSearch\Index')
            ->setMethods(['beforeSave'])
            ->getMock();

        $this->assertCount(1, $index->getEventManager()->listeners('Model.beforeSave'));
    }
}
