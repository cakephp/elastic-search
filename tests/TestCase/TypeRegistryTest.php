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
 * @since         0.5.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Type;
use Cake\ElasticSearch\TypeRegistry;
use Cake\TestSuite\TestCase;

/**
 * Used to test correct class is instantiated when using TypeRegistry::get();
 */
class MyUsersType extends Type
{

    /**
     * Overrides default table name
     *
     * @var string
     */
    protected $_name = 'users';
}


/**
 * Test case for TypeRegistry
 */
class TypeRegistryTest extends TestCase
{

    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        Configure::write('App.namespace', 'TestApp');
    }

    /**
     * tear down
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        TypeRegistry::clear();
    }

    /**
     * Test the exists() method.
     *
     * @return void
     */
    public function testExists()
    {
        $this->assertFalse(TypeRegistry::exists('Articles'));

        TypeRegistry::get('Articles', ['name' => 'articles']);
        $this->assertTrue(TypeRegistry::exists('Articles'));
    }

    /**
     * Test the exists() method with plugin-prefixed models.
     *
     * @return void
     */
    public function testExistsPlugin()
    {
        $this->assertFalse(TypeRegistry::exists('Comments'));
        $this->assertFalse(TypeRegistry::exists('TestPlugin.Comments'));

        TypeRegistry::get('TestPlugin.Comments', ['type' => 'comments']);
        $this->assertFalse(TypeRegistry::exists('Comments'), 'The Comments key should not be populated');
        $this->assertTrue(TypeRegistry::exists('TestPlugin.Comments'), 'The plugin.alias key should now be populated');
    }

    /**
     * Test getting instances from the registry.
     *
     * @return void
     */
    public function testGet()
    {
        $result = TypeRegistry::get('Articles', [
            'name' => 'my_articles',
        ]);
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('my_articles', $result->name());

        $result2 = TypeRegistry::get('Articles');
        $this->assertSame($result, $result2);
        $this->assertEquals('my_articles', $result->name());
    }

    /**
     * Are auto-models instanciated correctly? How about when they have an alias?
     *
     * @return void
     */
    public function testGetFallbacks()
    {
        $result = TypeRegistry::get('Droids');
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('droids', $result->name());

        $result = TypeRegistry::get('R2D2', ['className' => 'Droids']);
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('r2_d2', $result->name(), 'The name should be derived from the alias');

        $result = TypeRegistry::get('C3P0', ['className' => 'Droids', 'name' => 'droids']);
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('droids', $result->name(), 'The name should be taken from options');

        $result = TypeRegistry::get('Funky.Chipmunks');
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('chipmunks', $result->name(), 'The name should be derived from the alias');

        $result = TypeRegistry::get('Awesome', ['className' => 'Funky.Monkies']);
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('awesome', $result->name(), 'The name should be derived from the alias');

        $result = TypeRegistry::get('Stuff', ['className' => 'Cake\ElasticSearch\Type']);
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $result);
        $this->assertEquals('stuff', $result->name(), 'The name should be derived from the alias');
    }

    /**
     * Test get with config throws an exception if the alias exists already.
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You cannot configure "Users", it already exists in the registry.
     * @return void
     */
    public function testGetExistingWithConfigData()
    {
        $users = TypeRegistry::get('Users');
        TypeRegistry::get('Users', ['name' => 'my_users']);
    }

    /**
     * Test get() can be called several times with the same option without
     * throwing an exception.
     *
     * @return void
     */
    public function testGetWithSameOption()
    {
        $result = TypeRegistry::get('Users', ['className' => __NAMESPACE__ . '\MyUsersType']);
        $result2 = TypeRegistry::get('Users', ['className' => __NAMESPACE__ . '\MyUsersType']);
        $this->assertEquals($result, $result2);
    }

    /**
     * Tests that tables can be instantiated based on conventions
     * and using plugin notation
     *
     * @return void
     */
    public function testGetWithConventions()
    {
        $this->markTestIncomplete();
        $table = TypeRegistry::get('articles');
        $this->assertInstanceOf('TestApp\Model\Type\ArticlesTable', $table);
        $table = TypeRegistry::get('Articles');
        $this->assertInstanceOf('TestApp\Model\Table\ArticlesTable', $table);

        $table = TypeRegistry::get('authors');
        $this->assertInstanceOf('TestApp\Model\Table\AuthorsTable', $table);
        $table = TypeRegistry::get('Authors');
        $this->assertInstanceOf('TestApp\Model\Table\AuthorsTable', $table);
    }

    /**
     * Test get() with plugin syntax aliases
     *
     * @return void
     */
    public function testGetPlugin()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');
        $table = TypeRegistry::get('TestPlugin.TestPluginComments');

        $this->assertInstanceOf('TestPlugin\Model\Table\TestPluginCommentsTable', $table);
        $this->assertFalse(
            TypeRegistry::exists('TestPluginComments'),
            'Short form should NOT exist'
        );
        $this->assertTrue(
            TypeRegistry::exists('TestPlugin.TestPluginComments'),
            'Long form should exist'
        );

        $second = TypeRegistry::get('TestPlugin.TestPluginComments');
        $this->assertSame($table, $second, 'Can fetch long form');
    }

    /**
     * Test get() with same-alias models in different plugins
     *
     * There should be no internal cache-confusion
     *
     * @return void
     */
    public function testGetMultiplePlugins()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');
        Plugin::load('TestPluginTwo');

        $app = TypeRegistry::get('Comments');
        $plugin1 = TypeRegistry::get('TestPlugin.Comments');
        $plugin2 = TypeRegistry::get('TestPluginTwo.Comments');

        $this->assertInstanceOf('Cake\ORM\Table', $app, 'Should be an app table instance');
        $this->assertInstanceOf('TestPlugin\Model\Table\CommentsTable', $plugin1, 'Should be a plugin 1 table instance');
        $this->assertInstanceOf('TestPluginTwo\Model\Table\CommentsTable', $plugin2, 'Should be a plugin 2 table instance');

        $plugin2 = TypeRegistry::get('TestPluginTwo.Comments');
        $plugin1 = TypeRegistry::get('TestPlugin.Comments');
        $app = TypeRegistry::get('Comments');

        $this->assertInstanceOf('Cake\ORM\Table', $app, 'Should still be an app table instance');
        $this->assertInstanceOf('TestPlugin\Model\Table\CommentsTable', $plugin1, 'Should still be a plugin 1 table instance');
        $this->assertInstanceOf('TestPluginTwo\Model\Table\CommentsTable', $plugin2, 'Should still be a plugin 2 table instance');
    }

    /**
     * Test get() with plugin aliases + className option.
     *
     * @return void
     */
    public function testGetPluginWithClassNameOption()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');
        $table = TypeRegistry::get('Comments', [
            'className' => 'TestPlugin.TestPluginComments',
        ]);
        $class = 'TestPlugin\Model\Table\TestPluginCommentsTable';
        $this->assertInstanceOf($class, $table);
        $this->assertFalse(TypeRegistry::exists('TestPluginComments'), 'Class name should not exist');
        $this->assertFalse(TypeRegistry::exists('TestPlugin.TestPluginComments'), 'Full class alias should not exist');
        $this->assertTrue(TypeRegistry::exists('Comments'), 'Class name should exist');

        $second = TypeRegistry::get('Comments');
        $this->assertSame($table, $second);
    }

    /**
     * Test get() with full namespaced classname
     *
     * @return void
     */
    public function testGetPluginWithFullNamespaceName()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');
        $class = 'TestPlugin\Model\Table\TestPluginCommentsTable';
        $table = TypeRegistry::get('Comments', [
            'className' => $class,
        ]);
        $this->assertInstanceOf($class, $table);
        $this->assertFalse(TypeRegistry::exists('TestPluginComments'), 'Class name should not exist');
        $this->assertFalse(TypeRegistry::exists('TestPlugin.TestPluginComments'), 'Full class alias should not exist');
        $this->assertTrue(TypeRegistry::exists('Comments'), 'Class name should exist');
    }

    /**
     * Tests that table options can be pre-configured for the factory method
     *
     * @return void
     */
    public function testConfigAndBuild()
    {
        $this->markTestIncomplete();
        TypeRegistry::clear();
        $map = TypeRegistry::config();
        $this->assertEquals([], $map);

        $connection = ConnectionManager::get('test', false);
        $options = ['connection' => $connection];
        TypeRegistry::config('users', $options);
        $map = TypeRegistry::config();
        $this->assertEquals(['users' => $options], $map);
        $this->assertEquals($options, TypeRegistry::config('users'));

        $schema = ['id' => ['type' => 'rubbish']];
        $options += ['schema' => $schema];
        TypeRegistry::config('users', $options);

        $table = TypeRegistry::get('users', ['table' => 'users']);
        $this->assertInstanceOf('Cake\ORM\Table', $table);
        $this->assertEquals('users', $table->table());
        $this->assertEquals('users', $table->alias());
        $this->assertSame($connection, $table->connection());
        $this->assertEquals(array_keys($schema), $table->schema()->columns());
        $this->assertEquals($schema['id']['type'], $table->schema()->column('id')['type']);

        TypeRegistry::clear();
        $this->assertEmpty(TypeRegistry::config());

        TypeRegistry::config('users', $options);
        $table = TypeRegistry::get('users', ['className' => __NAMESPACE__ . '\MyUsersTable']);
        $this->assertInstanceOf(__NAMESPACE__ . '\MyUsersTable', $table);
        $this->assertEquals('users', $table->table());
        $this->assertEquals('users', $table->alias());
        $this->assertSame($connection, $table->connection());
        $this->assertEquals(array_keys($schema), $table->schema()->columns());
        $this->assertEquals($schema['id']['type'], $table->schema()->column('id')['type']);
    }

    /**
     * Tests that table options can be pre-configured with a single validator
     *
     * @return void
     */
    public function testConfigWithSingleValidator()
    {
        $this->markTestIncomplete();
        $validator = new Validator();

        TypeRegistry::config('users', ['validator' => $validator]);
        $table = TypeRegistry::get('users');

        $this->assertSame($table->validator('default'), $validator);
    }

    /**
     * Tests that table options can be pre-configured with multiple validators
     *
     * @return void
     */
    public function testConfigWithMultipleValidators()
    {
        $this->markTestIncomplete();
        $validator1 = new Validator();
        $validator2 = new Validator();
        $validator3 = new Validator();

        TypeRegistry::config('users', [
            'validator' => [
                'default' => $validator1,
                'secondary' => $validator2,
                'tertiary' => $validator3,
            ]
        ]);
        $table = TypeRegistry::get('users');

        $this->assertSame($table->validator('default'), $validator1);
        $this->assertSame($table->validator('secondary'), $validator2);
        $this->assertSame($table->validator('tertiary'), $validator3);
    }

    /**
     * Test setting an instance.
     *
     * @return void
     */
    public function testSet()
    {
        $this->markTestIncomplete();
        $mock = $this->getMock('Cake\ORM\Table');
        $this->assertSame($mock, TypeRegistry::set('Articles', $mock));
        $this->assertSame($mock, TypeRegistry::get('Articles'));
    }

    /**
     * Test setting an instance with plugin syntax aliases
     *
     * @return void
     */
    public function testSetPlugin()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');

        $mock = $this->getMock('TestPlugin\Model\Table\CommentsTable');

        $this->assertSame($mock, TypeRegistry::set('TestPlugin.Comments', $mock));
        $this->assertSame($mock, TypeRegistry::get('TestPlugin.Comments'));
    }

    /**
     * Tests remove an instance
     *
     * @return void
     */
    public function testRemove()
    {
        $this->markTestIncomplete();
        $first = TypeRegistry::get('Comments');

        $this->assertTrue(TypeRegistry::exists('Comments'));

        TypeRegistry::remove('Comments');
        $this->assertFalse(TypeRegistry::exists('Comments'));

        $second = TypeRegistry::get('Comments');

        $this->assertNotSame($first, $second, 'Should be different objects, as the reference to the first was destroyed');
        $this->assertTrue(TypeRegistry::exists('Comments'));
    }

    /**
     * testRemovePlugin
     *
     * Removing a plugin-prefixed model should not affect any other
     * plugin-prefixed model, or app model.
     * Removing an app model should not affect any other
     * plugin-prefixed model.
     *
     * @return void
     */
    public function testRemovePlugin()
    {
        $this->markTestIncomplete();
        Plugin::load('TestPlugin');
        Plugin::load('TestPluginTwo');

        $app = TypeRegistry::get('Comments');
        TypeRegistry::get('TestPlugin.Comments');
        $plugin = TypeRegistry::get('TestPluginTwo.Comments');

        $this->assertTrue(TypeRegistry::exists('Comments'));
        $this->assertTrue(TypeRegistry::exists('TestPlugin.Comments'));
        $this->assertTrue(TypeRegistry::exists('TestPluginTwo.Comments'));

        TypeRegistry::remove('TestPlugin.Comments');

        $this->assertTrue(TypeRegistry::exists('Comments'));
        $this->assertFalse(TypeRegistry::exists('TestPlugin.Comments'));
        $this->assertTrue(TypeRegistry::exists('TestPluginTwo.Comments'));

        $app2 = TypeRegistry::get('Comments');
        $plugin2 = TypeRegistry::get('TestPluginTwo.Comments');

        $this->assertSame($app, $app2, 'Should be the same Comments object');
        $this->assertSame($plugin, $plugin2, 'Should be the same TestPluginTwo.Comments object');

        TypeRegistry::remove('Comments');

        $this->assertFalse(TypeRegistry::exists('Comments'));
        $this->assertFalse(TypeRegistry::exists('TestPlugin.Comments'));
        $this->assertTrue(TypeRegistry::exists('TestPluginTwo.Comments'));

        $plugin3 = TypeRegistry::get('TestPluginTwo.Comments');

        $this->assertSame($plugin, $plugin3, 'Should be the same TestPluginTwo.Comments object');
    }
}
