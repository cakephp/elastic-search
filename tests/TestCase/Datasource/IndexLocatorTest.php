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
 * @since     0.5.0
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\Core\Configure;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Datasource\IndexLocator;
use Cake\ElasticSearch\Exception\MissingIndexClassException;

;
use Cake\TestSuite\TestCase;

/**
 * Test case for IndexLocator
 */
class IndexLocatorTest extends TestCase
{
    /**
     * setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        Configure::write('App.namespace', 'TestApp');

        $this->locator = new IndexLocator();
    }

    /**
     * Test the exists() method.
     *
     * @return void
     */
    public function testExists()
    {
        $this->assertFalse($this->locator->exists('Articles'));

        $this->locator->get('Articles', ['name' => 'articles']);
        $this->assertTrue($this->locator->exists('Articles'));
    }

    /**
     * Test the exists() method with plugin-prefixed models.
     *
     * @return void
     */
    public function testExistsPlugin()
    {
        $this->assertFalse($this->locator->exists('Comments'));
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'));

        $this->locator->get('TestPlugin.Comments', ['name' => 'comments']);
        $this->assertFalse($this->locator->exists('Comments'), 'The Comments key should not be populated');
        $this->assertTrue($this->locator->exists('TestPlugin.Comments'), 'The plugin.alias key should now be populated');
    }

    /**
     * Test getting instances from the registry.
     *
     * @return void
     */
    public function testGet()
    {
        $result = $this->locator->get(
            'Articles',
            [
                'name' => 'my_articles',
            ]
        );
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('my_articles', $result->getName());

        $result2 = $this->locator->get('Articles');
        $this->assertSame($result, $result2);
        $this->assertSame('my_articles', $result->getName());
    }

    /**
     * Are auto-models instanciated correctly? How about when they have an alias?
     *
     * @return void
     */
    public function testGetFallbacks()
    {
        $result = $this->locator->get('Droids');
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('droids', $result->getName());

        $result = $this->locator->get('R2D2', ['className' => 'Droids']);
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame(
            'r2_d2',
            $result->getName(),
            'The name should be derived from the alias'
        );

        $result = $this->locator->get(
            'C3P0',
            ['className' => 'Droids', 'name' => 'droids']
        );
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('droids', $result->getName(), 'The name should be taken from options');

        $result = $this->locator->get('Funky.Chipmunks');
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('chipmunks', $result->getName(), 'The name should be derived from the alias');

        $result = $this->locator->get('Awesome', ['className' => 'Funky.Monkies']);
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('awesome', $result->getName(), 'The name should be derived from the alias');

        $result = $this->locator->get('Stuff', ['className' => 'Cake\ElasticSearch\Index']);
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $result);
        $this->assertSame('stuff', $result->getName(), 'The name should be derived from the alias');
    }

    /**
     * Test get() with fallbacks disabled
     *
     * @return void
     */
    public function testGetNoFallback()
    {
        $this->locator->allowFallbackClass(false);
        $this->expectException(MissingIndexClassException::class);
        $this->locator->get('Articles');
    }

    /**
     * Test get with config throws an exception if the alias exists already.
     *
     * @return                   void
     */
    public function testGetExistingWithConfigData()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('You cannot configure "Users", it already exists in the registry.');
        $users = $this->locator->get('Users');
        $this->locator->get('Users', ['name' => 'my_users']);
    }

    /**
     * Test get() can be called several times with the same option without
     * throwing an exception.
     *
     * @return void
     */
    public function testGetWithSameOption()
    {
        $result = $this->locator->get('Users', ['className' => MyUsersIndex::class]);
        $result2 = $this->locator->get('Users', ['className' => MyUsersIndex::class]);
        $this->assertEquals($result, $result2);
    }

    /**
     * Test get() with plugin syntax aliases
     *
     * @return void
     */
    public function testGetPlugin()
    {
        $this->loadPlugins(['TestPlugin']);
        $table = $this->locator->get('TestPlugin.Comments');

        $this->assertInstanceOf('TestPlugin\Model\Index\CommentsIndex', $table);
        $this->assertFalse(
            $this->locator->exists('Comments'),
            'Short form should NOT exist'
        );
        $this->assertTrue(
            $this->locator->exists('TestPlugin.Comments'),
            'Long form should exist'
        );

        $second = $this->locator->get('TestPlugin.Comments');
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
        $this->loadPlugins(['TestPlugin', 'TestPluginTwo']);

        $app = $this->locator->get('Comments');
        $plugin1 = $this->locator->get('TestPlugin.Comments');
        $plugin2 = $this->locator->get('TestPluginTwo.Comments');

        $this->assertInstanceOf('Cake\ElasticSearch\Index', $app, 'Should be a generic instance');
        $this->assertInstanceOf('TestPlugin\Model\Index\CommentsIndex', $plugin1, 'Should be a concrete class');
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $plugin2, 'Should be a plugin 2 generic instance');

        $plugin2 = $this->locator->get('TestPluginTwo.Comments');
        $plugin1 = $this->locator->get('TestPlugin.Comments');
        $app = $this->locator->get('Comments');

        $this->assertInstanceOf('Cake\ElasticSearch\Index', $app, 'Should still be a generic instance');
        $this->assertInstanceOf('TestPlugin\Model\Index\CommentsIndex', $plugin1, 'Should still be a concrete class');
        $this->assertInstanceOf('Cake\ElasticSearch\Index', $plugin2, 'Should still be a plugin 2 generic instance');
    }

    /**
     * Test get() with plugin aliases + className option.
     *
     * @return void
     */
    public function testGetPluginWithClassNameOption()
    {
        $this->loadPlugins(['TestPlugin']);
        $table = $this->locator->get(
            'MyComments',
            [
            'className' => 'TestPlugin.Comments',
            ]
        );
        $class = 'TestPlugin\Model\Index\CommentsIndex';
        $this->assertInstanceOf($class, $table);
        $this->assertFalse($this->locator->exists('Comments'), 'Class name should not exist');
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue($this->locator->exists('MyComments'), 'Class name should exist');

        $second = $this->locator->get('MyComments');
        $this->assertSame($table, $second);
    }

    /**
     * Test get() with full namespaced classname
     *
     * @return void
     */
    public function testGetPluginWithFullNamespaceName()
    {
        $this->loadPlugins(['TestPlugin']);
        $class = 'TestPlugin\Model\Index\CommentsIndex';
        $table = $this->locator->get(
            'Comments',
            ['className' => $class]
        );
        $this->assertInstanceOf($class, $table);
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue($this->locator->exists('Comments'), 'Class name should exist');
    }

    /**
     * Test setting an instance.
     *
     * @return void
     */
    public function testSet()
    {
        $mock = $this->getMockBuilder('Cake\ElasticSearch\Index')->getMock();
        $this->assertSame($mock, $this->locator->set('Articles', $mock));
        $this->assertSame($mock, $this->locator->get('Articles'));
    }

    /**
     * Test setting an instance with plugin syntax aliases
     *
     * @return void
     */
    public function testSetPlugin()
    {
        $this->loadPlugins(['TestPlugin']);

        $mock = $this->getMockBuilder('TestPlugin\Model\Index\CommentsIndex')
            ->getMock();

        $this->assertSame($mock, $this->locator->set('TestPlugin.Comments', $mock));
        $this->assertSame($mock, $this->locator->get('TestPlugin.Comments'));
    }

    /**
     * Tests remove an instance
     *
     * @return void
     */
    public function testRemove()
    {
        $first = $this->locator->get('Comments');

        $this->assertTrue($this->locator->exists('Comments'));

        $this->locator->remove('Comments');
        $this->assertFalse($this->locator->exists('Comments'));

        $second = $this->locator->get('Comments');

        $this->assertNotSame(
            $first,
            $second,
            'Should be different, as the reference to the first was destroyed'
        );
        $this->assertTrue($this->locator->exists('Comments'));
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
        $this->loadPlugins(['TestPlugin', 'TestPluginTwo']);

        $app = $this->locator->get('Comments');
        $this->locator->get('TestPlugin.Comments');
        $plugin = $this->locator->get('TestPluginTwo.Comments');

        $this->assertTrue($this->locator->exists('Comments'));
        $this->assertTrue($this->locator->exists('TestPlugin.Comments'));
        $this->assertTrue($this->locator->exists('TestPluginTwo.Comments'));

        $this->locator->remove('TestPlugin.Comments');

        $this->assertTrue($this->locator->exists('Comments'));
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'));
        $this->assertTrue($this->locator->exists('TestPluginTwo.Comments'));

        $app2 = $this->locator->get('Comments');
        $plugin2 = $this->locator->get('TestPluginTwo.Comments');

        $this->assertSame($app, $app2, 'Should be the same Comments object');
        $this->assertSame($plugin, $plugin2, 'Should be the same TestPluginTwo.Comments object');

        $this->locator->remove('Comments');

        $this->assertFalse($this->locator->exists('Comments'));
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'));
        $this->assertTrue($this->locator->exists('TestPluginTwo.Comments'));

        $plugin3 = $this->locator->get('TestPluginTwo.Comments');

        $this->assertSame($plugin, $plugin3, 'Should be the same TestPluginTwo.Comments object');
    }

    public function testSetFallbackClassName(): void
    {
        $this->locator->setFallbackClassName('TestApp\Model\Index\UsersIndex');

        $result = $this->locator->get('Droids');
        $this->assertInstanceOf('TestApp\Model\Index\UsersIndex', $result);

        $this->locator->setFallbackClassName(Index::class);
    }
}
