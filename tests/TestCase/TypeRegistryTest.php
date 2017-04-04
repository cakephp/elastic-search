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
     * Test get() with plugin syntax aliases
     *
     * @return void
     */
    public function testGetPlugin()
    {
        Plugin::load('TestPlugin');
        $table = TypeRegistry::get('TestPlugin.Comments');

        $this->assertInstanceOf('TestPlugin\Model\Type\CommentsType', $table);
        $this->assertFalse(
            TypeRegistry::exists('Comments'),
            'Short form should NOT exist'
        );
        $this->assertTrue(
            TypeRegistry::exists('TestPlugin.Comments'),
            'Long form should exist'
        );

        $second = TypeRegistry::get('TestPlugin.Comments');
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
        Plugin::load('TestPlugin');
        Plugin::load('TestPluginTwo');

        $app = TypeRegistry::get('Comments');
        $plugin1 = TypeRegistry::get('TestPlugin.Comments');
        $plugin2 = TypeRegistry::get('TestPluginTwo.Comments');

        $this->assertInstanceOf('Cake\ElasticSearch\Type', $app, 'Should be a generic instance');
        $this->assertInstanceOf('TestPlugin\Model\Type\CommentsType', $plugin1, 'Should be a concrete class');
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $plugin2, 'Should be a plugin 2 generic instance');

        $plugin2 = TypeRegistry::get('TestPluginTwo.Comments');
        $plugin1 = TypeRegistry::get('TestPlugin.Comments');
        $app = TypeRegistry::get('Comments');

        $this->assertInstanceOf('Cake\ElasticSearch\Type', $app, 'Should still be a generic instance');
        $this->assertInstanceOf('TestPlugin\Model\Type\CommentsType', $plugin1, 'Should still be a concrete class');
        $this->assertInstanceOf('Cake\ElasticSearch\Type', $plugin2, 'Should still be a plugin 2 generic instance');
    }

    /**
     * Test get() with plugin aliases + className option.
     *
     * @return void
     */
    public function testGetPluginWithClassNameOption()
    {
        Plugin::load('TestPlugin');
        $table = TypeRegistry::get('MyComments', [
            'className' => 'TestPlugin.Comments',
        ]);
        $class = 'TestPlugin\Model\Type\CommentsType';
        $this->assertInstanceOf($class, $table);
        $this->assertFalse(TypeRegistry::exists('Comments'), 'Class name should not exist');
        $this->assertFalse(TypeRegistry::exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue(TypeRegistry::exists('MyComments'), 'Class name should exist');

        $second = TypeRegistry::get('MyComments');
        $this->assertSame($table, $second);
    }

    /**
     * Test get() with full namespaced classname
     *
     * @return void
     */
    public function testGetPluginWithFullNamespaceName()
    {
        Plugin::load('TestPlugin');
        $class = 'TestPlugin\Model\Type\CommentsType';
        $table = TypeRegistry::get('Comments', [
            'className' => $class,
        ]);
        $this->assertInstanceOf($class, $table);
        $this->assertFalse(TypeRegistry::exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue(TypeRegistry::exists('Comments'), 'Class name should exist');
    }

    /**
     * Test setting an instance.
     *
     * @return void
     */
    public function testSet()
    {
        $mock = $this->getMockBuilder('Cake\ElasticSearch\Type')->getMock();
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
        Plugin::load('TestPlugin');

        $mock = $this->getMockBuilder('TestPlugin\Model\Type\CommentsType')->getMock();

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
