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
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;

/**
 * Tests the Type class
 *
 */
class TypeTest extends TestCase
{
    public $fixtures = ['plugin.cake/elastic_search.articles'];

    public function setUp()
    {
        parent::setUp();
        $this->type = new Type([
            'name' => 'articles',
            'connection' => ConnectionManager::get('test')
        ]);
    }

    /**
     * Tests that calling find will return a query object
     *
     * @return void
     */
    public function testFindAll()
    {
        $query = $this->type->find('all');
        $this->assertInstanceOf('Cake\ElasticSearch\Query', $query);
        $this->assertSame($this->type, $query->repository());
    }

    /**
     * Tests that calling find will return a query object
     *
     * @expectedException \Cake\Datasource\Exception\RecordNotFoundException
     * @return void
     */
    public function testFindAllWithFirstOrFail()
    {
        $this->type->find('all')->where(['id' => '999999999'])->firstOrFail();
    }

    /**
     * Tests that table() is implemented as QueryTrait relies on.
     *
     * @return void
     */
    public function testTable()
    {
        $this->assertSame('articles', $this->type->table());
    }

    /**
     * Test the default entityClass.
     *
     * @return void
     */
    public function testEntityClassDefault()
    {
        $this->assertEquals('\Cake\ElasticSearch\Document', $this->type->entityClass());
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the App namespace
     *
     * @return void
     */
    public function testTableClassInApp()
    {
        $class = $this->getMockClass('Cake\ElasticSearch\Document');
        class_alias($class, 'App\Model\Document\TestUser');

        $type = new Type();
        $this->assertEquals(
            'App\Model\Document\TestUser',
            $type->entityClass('TestUser')
        );
    }

    /**
     * Tests that using a simple string for entityClass will try to
     * load the class from the Plugin namespace when using plugin notation
     *
     * @return void
     */
    public function testTableClassInPlugin()
    {
        $class = $this->getMockClass('\Cake\ElasticSearch\Document');
        class_alias($class, 'MyPlugin\Model\Document\SuperUser');

        $type = new Type();
        $this->assertEquals(
            'MyPlugin\Model\Document\SuperUser',
            $type->entityClass('MyPlugin.SuperUser')
        );
    }

    /**
     * Tests the get method
     *
     * @return void
     */
    public function testGet()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'foo',
            'connection' => $connection
        ]);

        $index = $this->getMockBuilder('Elastica\Index')
            ->disableOriginalConstructor()
            ->getMock();

        $internalType = $this->getMockBuilder('Elastica\Type')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('getIndex')
            ->will($this->returnValue($index));

        $index->expects($this->once())
            ->method('getType')
            ->will($this->returnValue($internalType));

        $document = $this->getMock('Elastica\Document', ['getId', 'getData']);
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

        $result = $type->get('foo', ['bar' => 'baz']);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertEquals(['a' => 'b', 'id' => 'foo'], $result->toArray());
        $this->assertFalse($result->dirty());
        $this->assertFalse($result->isNew());
        $this->assertEquals('foo', $result->source());
    }

    /**
     * Test that newEntity is wired up.
     *
     * @return void
     */
    public function testNewEntity()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'articles',
            'connection' => $connection
        ]);
        $data = [
            'title' => 'A newer title'
        ];
        $result = $type->newEntity($data);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertSame($data, $result->toArray());
        $this->assertEquals('articles', $result->source());
    }

    /**
     * Test that newEntities is wired up.
     *
     * @return void
     */
    public function testNewEntities()
    {
        $connection = $this->getMock(
            'Cake\ElasticSearch\Datasource\Connection',
            ['getIndex']
        );
        $type = new Type([
            'name' => 'articles',
            'connection' => $connection
        ]);
        $data = [
            [
                'title' => 'A newer title'
            ],
            [
                'title' => 'A second title'
            ],
        ];
        $result = $type->newEntities($data);
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
            new Document([
                'title' => 'First',
                'body' => 'Some new content'
            ], [
                'markNew' => true
            ]),
            new Document([
                'title' => 'Second',
                'body' => 'Some new content'
            ], [
                'markNew' => true
            ])
        ];

        $result = $this->type->saveMany($entities);
        $this->assertTrue($result);
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveNew()
    {
        $doc = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => true]);
        $this->assertSame($doc, $this->type->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');
        $this->assertFalse($doc->isNew(), 'Not new anymore.');
        $this->assertFalse($doc->dirty(), 'Not dirty anymore.');

        $result = $this->type->get($doc->id);
        $this->assertEquals($doc->title, $result->title);
        $this->assertEquals($doc->body, $result->body);
        $this->assertEquals('articles', $result->source());
    }

    /**
     * Test saving a new document.
     *
     * @return void
     */
    public function testSaveUpdate()
    {
        $doc = new Document([
            'id' => '123',
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => false]);
        $this->assertSame($doc, $this->type->save($doc));
        $this->assertFalse($doc->isNew(), 'Not new.');
        $this->assertFalse($doc->dirty(), 'Not dirty anymore.');
        $this->assertEquals('articles', $doc->source());
    }

    /**
     * Test saving a new document that contains errors
     *
     * @return void
     */
    public function testSaveDoesNotSaveDocumentWithErrors()
    {
        $doc = new Document([
            'id' => '123',
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => false]);
        $doc->errors(['title' => ['bad news']]);
        $this->assertFalse($this->type->save($doc), 'Should not save.');
    }

    /**
     * Test save triggers events.
     *
     * @return void
     */
    public function testSaveEvents()
    {
        $doc = $this->type->get(1);
        $doc->title = 'A new title';

        $called = 0;
        $this->type->eventManager()->on(
            'Model.beforeSave',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->type->eventManager()->on(
            'Model.afterSave',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $options);
                $this->assertSame($doc, $entity);
                $this->assertFalse($doc->isNew(), 'Should not be new');
                $this->assertFalse($doc->dirty(), 'Should not be dirty');
            }
        );
        $this->type->save($doc);
        $this->assertEquals(2, $called);
    }

    /**
     * Test beforeSave abort.
     *
     * @return void
     */
    public function testSaveBeforeSaveAbort()
    {
        $doc = $this->type->get(1);
        $doc->title = 'new title';
        $this->type->eventManager()->on('Model.beforeSave', function ($event, $entity, $options) use ($doc) {
            $event->stopPropagation();

            return 'kaboom';
        });
        $this->type->eventManager()->on('Model.afterSave', function () {
            $this->fail('Should not be fired');
        });
        $this->assertSame('kaboom', $this->type->save($doc));
    }

    /**
     * Test save with embedded documents.
     *
     * @return void
     */
    public function testSaveEmbedOne()
    {
        $entity = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'user' => new Document(['username' => 'sarah'])
        ]);
        $this->type->embedOne('User');
        $this->type->save($entity);

        $compare = $this->type->get($entity->id);
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
        $entity = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content',
            'comments' => [
                new Document(['comment' => 'Nice post']),
                new Document(['comment' => 'Awesome!']),
            ]
        ]);
        $this->type->embedMany('Comments');
        $this->type->save($entity);

        $compare = $this->type->get($entity->id);
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
        $this->type->eventManager()->on('Model.buildRules', function ($event, $rules) {
            $rules->addCreate(function ($doc) {
                return 'Did not work';
            }, ['errorField' => 'name']);
        });

        $doc = new Document(['title' => 'rules are checked']);
        $this->assertFalse($this->type->save($doc), 'Save should fail');

        $doc->clean();
        $doc->id = 12345;
        $doc->isNew(false);
        $this->assertSame($doc, $this->type->save($doc), 'Save should pass, not new anymore.');
    }

    /**
     * Test that rules can prevent save.
     *
     * @return void
     */
    public function testSaveWithRulesUpdate()
    {
        $this->type->eventManager()->on('Model.buildRules', function ($event, $rules) {
            $rules->addUpdate(function ($doc) {
                return 'Did not work';
            }, ['errorField' => 'name']);
        });

        $doc = new Document(['title' => 'update rules'], ['markNew' => false]);
        $this->assertFalse($this->type->save($doc), 'Save should fail');
    }

    /**
     * Test to make sure double save works correctly
     *
     * @return void
     */
    public function testDoubleSave()
    {
        $doc = new Document([
            'title' => 'A brand new article',
            'body' => 'Some new content'
        ], ['markNew' => true]);
        $this->assertSame($doc, $this->type->save($doc));
        $this->assertNotEmpty($doc->id, 'Should get an id');
        $this->assertNotEmpty($doc->_version, 'Should get a version');

        $this->assertSame($doc, $this->type->save($doc));
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
        $doc = $this->type->get(1);
        $this->assertTrue($this->type->delete($doc));

        $dead = $this->type->find()->where(['id' => 1])->first();
        $this->assertNull($dead, 'No record.');
    }

    /**
     * Test deletion prevented by rules
     *
     * @return void
     */
    public function testDeleteRules()
    {
        $this->type->rulesChecker()->addDelete(function () {
            return 'not good';
        }, ['errorField' => 'title']);
        $doc = $this->type->get(1);

        $this->assertFalse($this->type->delete($doc));
        $this->assertNotEmpty($doc->errors('title'));
    }

    /**
     * Test delete triggers events.
     *
     * @return void
     */
    public function testDeleteEvents()
    {
        $called = 0;
        $doc = $this->type->get(1);
        $this->type->eventManager()->on(
            'Model.beforeDelete',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->type->eventManager()->on(
            'Model.afterDelete',
            function ($event, $entity, $options) use ($doc, &$called) {
                $called++;
                $this->assertSame($doc, $entity);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $this->assertTrue($this->type->delete($doc));
        $this->assertEquals(2, $called);
    }

    /**
     * Test beforeDelete abort.
     *
     * @return void
     */
    public function testDeleteBeforeDeleteAbort()
    {
        $doc = $this->type->get(1);
        $this->type->eventManager()->on('Model.beforeDelete', function ($event, $entity, $options) use ($doc) {
            $event->stopPropagation();

            return 'kaboom';
        });
        $this->type->eventManager()->on('Model.afterDelete', function () {
            $this->fail('Should not be fired');
        });
        $this->assertSame('kaboom', $this->type->delete($doc));
    }

    /**
     * Test deleting a new document
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Deleting requires an "id" value.
     * @return void
     */
    public function testDeleteMissing()
    {
        $doc = new Document(['title' => 'not there.']);
        $this->type->delete($doc);
    }

    /**
     * Test getting and setting validators.
     *
     * @return void
     */
    public function testValidatorSetAndGet()
    {
        $result = $this->type->validator();

        $this->assertInstanceOf('Cake\Validation\Validator', $result);
        $this->assertSame($result, $this->type->validator(), 'validator instances are persistent');
        $this->assertSame($this->type, $result->provider('collection'), 'type bound as provider');
    }

    /**
     * Test buildValidator event
     *
     * @return void
     */
    public function testValidatorTriggerEvent()
    {
        $called = 0;
        $this->type->eventManager()->on('Model.buildValidator', function ($event, $validator, $name) use (&$called) {
            $called++;
            $this->assertInstanceOf('Cake\Validation\Validator', $validator);
            $this->assertEquals('default', $name);
        });
        $this->type->validator();
        $this->assertEquals(1, $called, 'Event not triggered');
    }

    /**
     * Test that exists works.
     *
     * @return void
     */
    public function testExists()
    {
        $this->assertFalse($this->type->exists(['id' => '999999']));
        $this->assertTrue($this->type->exists(['id' => '1']));
    }

    /**
     * Test that deleteAll works.
     *
     * @return void
     */
    public function testDeleteAll()
    {
        $result = $this->type->deleteAll(['title' => 'article']);
        $this->assertTrue($result);
        $this->assertEquals(0, $this->type->find()->count());
    }

    /**
     * Test that deleteAll works.
     *
     * @return void
     */
    public function testDeleteAllOnlySome()
    {
        $result = $this->type->deleteAll(['body' => 'cake']);
        $this->assertTrue($result);
        $this->assertEquals(1, $this->type->find()->count());
    }

    /**
     * Test the rules builder types
     *
     * @return void
     */
    public function testAddRules()
    {
        $this->type->eventManager()->on('Model.buildRules', function ($event, $rules) {
            $rules->add(function ($doc) {
                return false;
            });
        });
        $rules = $this->type->rulesChecker();
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
        $this->assertEquals($this->type->name(), $this->type->alias());
        $this->assertEquals('articles', $this->type->alias());
    }

    /**
     * Test hasField()
     *
     * @return void
     */
    public function testHasField()
    {
        $this->assertTrue($this->type->hasField('title'));
        $this->assertFalse($this->type->hasField('nope'));
    }

    /**
     * Test that Type implements the EventListenerInterface and some events.
     *
     * @return void
     */
    public function testImplementedEvents()
    {
        $this->assertInstanceOf('Cake\Event\EventListenerInterface', $this->type);

        $type = $this->getMock(
            'Cake\ElasticSearch\Type',
            ['beforeFind', 'beforeSave', 'afterSave', 'beforeDelete', 'afterDelete']
        );
        $result = $type->implementedEvents();
        $expected = [
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
        ];
        $this->assertEquals($expected, $result, 'Events do not match.');
    }
}
