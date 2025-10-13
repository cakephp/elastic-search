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
 * @since     0.5.0
 * @license   https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\Core\Configure;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\IndexLocator;
use Cake\ElasticSearch\Exception\MissingIndexClassException;
use Cake\ElasticSearch\Index;
use Cake\TestSuite\TestCase;
use TestApp\Model\Index\MyUsersIndex;
use TestApp\Model\Index\UsersIndex;
use TestPlugin\Model\Index\CommentsIndex;

/**
 * Test case for IndexLocator
 */
class IndexLocatorTest extends TestCase
{
    protected IndexLocator $locator;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('App.namespace', 'TestApp');

        $this->locator = new IndexLocator();
    }

    /**
     * Test the exists() method.
     */
    public function testExists(): void
    {
        $this->assertFalse($this->locator->exists('Articles'));

        $this->locator->get('Articles', ['name' => 'articles']);
        $this->assertTrue($this->locator->exists('Articles'));
    }

    /**
     * Test the exists() method with plugin-prefixed models.
     */
    public function testExistsPlugin(): void
    {
        $this->assertFalse($this->locator->exists('Comments'));
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'));

        $this->locator->get('TestPlugin.Comments', ['name' => 'comments']);
        $this->assertFalse($this->locator->exists('Comments'), 'The Comments key should not be populated');
        $this->assertTrue($this->locator->exists('TestPlugin.Comments'), 'The plugin.alias key should now be populated');
    }

    /**
     * Test getting instances from the registry.
     */
    public function testGet(): void
    {
        $result = $this->locator->get(
            'Articles',
            [
                'name' => 'my_articles',
            ],
        );
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('my_articles', $result->getName());

        $result2 = $this->locator->get('Articles');
        $this->assertSame($result, $result2);
        $this->assertSame('my_articles', $result->getName());
    }

    /**
     * Are auto-models instanciated correctly? How about when they have an alias?
     */
    public function testGetFallbacks(): void
    {
        $result = $this->locator->get('Droids');
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('droids', $result->getName());

        $result = $this->locator->get('R2D2', ['className' => 'Droids']);
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame(
            'r2_d2',
            $result->getName(),
            'The name should be derived from the alias',
        );

        $result = $this->locator->get(
            'C3P0',
            ['className' => 'Droids', 'name' => 'droids'],
        );
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('droids', $result->getName(), 'The name should be taken from options');

        $result = $this->locator->get('Funky.Chipmunks');
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('chipmunks', $result->getName(), 'The name should be derived from the alias');

        $result = $this->locator->get('Awesome', ['className' => 'Funky.Monkies']);
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('awesome', $result->getName(), 'The name should be derived from the alias');

        $result = $this->locator->get('Stuff', ['className' => Index::class]);
        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('stuff', $result->getName(), 'The name should be derived from the alias');
    }

    /**
     * Test get() with fallbacks disabled
     */
    public function testGetNoFallback(): void
    {
        $this->locator->allowFallbackClass(false);
        $this->expectException(MissingIndexClassException::class);
        $this->locator->get('Articles');
    }

    /**
     * Test get with config throws an exception if the alias exists already.
     */
    public function testGetExistingWithConfigData(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('You cannot configure `Users`, it already exists in the registry.');
        $this->locator->get('Users');
        $this->locator->get('Users', ['name' => 'my_users']);
    }

    /**
     * Test get() can be called several times with the same option without
     * throwing an exception.
     */
    public function testGetWithSameOption(): void
    {
        $result = $this->locator->get('Users', ['className' => MyUsersIndex::class]);
        $result2 = $this->locator->get('Users', ['className' => MyUsersIndex::class]);
        $this->assertEquals($result, $result2);
    }

    /**
     * Test get() with plugin syntax aliases
     */
    public function testGetPlugin(): void
    {
        $this->loadPlugins(['TestPlugin']);
        $table = $this->locator->get('TestPlugin.Comments');

        $this->assertInstanceOf(CommentsIndex::class, $table);
        $this->assertFalse(
            $this->locator->exists('Comments'),
            'Short form should NOT exist',
        );
        $this->assertTrue(
            $this->locator->exists('TestPlugin.Comments'),
            'Long form should exist',
        );

        $second = $this->locator->get('TestPlugin.Comments');
        $this->assertSame($table, $second, 'Can fetch long form');
    }

    /**
     * Test get() with same-alias models in different plugins
     *
     * There should be no internal cache-confusion
     */
    public function testGetMultiplePlugins(): void
    {
        $this->loadPlugins(['TestPlugin', 'TestPluginTwo']);

        $app = $this->locator->get('Comments');
        $plugin1 = $this->locator->get('TestPlugin.Comments');
        $plugin2 = $this->locator->get('TestPluginTwo.Comments');

        $this->assertInstanceOf(Index::class, $app, 'Should be a generic instance');
        $this->assertInstanceOf(CommentsIndex::class, $plugin1, 'Should be a concrete class');
        $this->assertInstanceOf(Index::class, $plugin2, 'Should be a plugin 2 generic instance');

        $plugin2 = $this->locator->get('TestPluginTwo.Comments');
        $plugin1 = $this->locator->get('TestPlugin.Comments');
        $app = $this->locator->get('Comments');

        $this->assertInstanceOf(Index::class, $app, 'Should still be a generic instance');
        $this->assertInstanceOf(CommentsIndex::class, $plugin1, 'Should still be a concrete class');
        $this->assertInstanceOf(Index::class, $plugin2, 'Should still be a plugin 2 generic instance');
    }

    /**
     * Test get() with plugin aliases + className option.
     */
    public function testGetPluginWithClassNameOption(): void
    {
        $this->loadPlugins(['TestPlugin']);
        $table = $this->locator->get(
            'MyComments',
            [
            'className' => 'TestPlugin.Comments',
            ],
        );
        $class = CommentsIndex::class;
        $this->assertInstanceOf($class, $table);
        $this->assertFalse($this->locator->exists('Comments'), 'Class name should not exist');
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue($this->locator->exists('MyComments'), 'Class name should exist');

        $second = $this->locator->get('MyComments');
        $this->assertSame($table, $second);
    }

    /**
     * Test get() with full namespaced classname
     */
    public function testGetPluginWithFullNamespaceName(): void
    {
        $this->loadPlugins(['TestPlugin']);
        $class = CommentsIndex::class;
        $table = $this->locator->get(
            'Comments',
            ['className' => $class],
        );
        $this->assertInstanceOf($class, $table);
        $this->assertFalse($this->locator->exists('TestPlugin.Comments'), 'Full class alias should not exist');
        $this->assertTrue($this->locator->exists('Comments'), 'Class name should exist');
    }

    /**
     * Test setting an instance.
     */
    public function testSet(): void
    {
        $mock = $this->getMockBuilder(Index::class)->getMock();
        $this->assertSame($mock, $this->locator->set('Articles', $mock));
        $this->assertSame($mock, $this->locator->get('Articles'));
    }

    /**
     * Test setting an instance with plugin syntax aliases
     */
    public function testSetPlugin(): void
    {
        $this->loadPlugins(['TestPlugin']);

        $mock = $this->getMockBuilder(CommentsIndex::class)
            ->getMock();

        $this->assertSame($mock, $this->locator->set('TestPlugin.Comments', $mock));
        $this->assertSame($mock, $this->locator->get('TestPlugin.Comments'));
    }

    /**
     * Tests remove an instance
     */
    public function testRemove(): void
    {
        $first = $this->locator->get('Comments');

        $this->assertTrue($this->locator->exists('Comments'));

        $this->locator->remove('Comments');
        $this->assertFalse($this->locator->exists('Comments'));

        $second = $this->locator->get('Comments');

        $this->assertNotSame(
            $first,
            $second,
            'Should be different, as the reference to the first was destroyed',
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
     */
    public function testRemovePlugin(): void
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
        $this->locator->setFallbackClassName(UsersIndex::class);

        $result = $this->locator->get('Droids');
        $this->assertInstanceOf(UsersIndex::class, $result);

        $this->locator->setFallbackClassName(Index::class);
    }

    /**
     * Test createInstance with connection option provided
     */
    public function testCreateInstanceWithConnectionOption(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $this->locator->get('TestIndex', [
            'connection' => $connection,
            'name' => 'test_index',
        ]);

        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('test_index', $result->getName());
    }

    /**
     * Test createInstance with className containing backslash (full namespace)
     */
    public function testCreateInstanceWithFullNamespaceClassName(): void
    {
        $result = $this->locator->get('TestIndex', [
            'className' => Index::class,
            'name' => 'custom_name',
        ]);

        $this->assertInstanceOf(Index::class, $result);
        $this->assertSame('custom_name', $result->getName());
    }

    /**
     * Test createInstance with fallback disabled and missing className
     */
    public function testCreateInstanceFallbackDisabledMissingClass(): void
    {
        $this->locator->allowFallbackClass(false);

        $this->expectException(MissingIndexClassException::class);
        $this->expectExceptionMessage('Index class NonExistentIndex could not be found.');

        $this->locator->get('NonExistentIndex');
    }

    /**
     * Test createInstance with name derivation from className without namespace
     */
    public function testCreateInstanceNameDerivationFromClassName(): void
    {
        // Test the branch where name is derived from className when no name is provided
        // and className doesn't contain backslash
        $this->locator->allowFallbackClass(true);

        $result = $this->locator->get('SomeCustomIndex', [
            // No name provided, className will be derived from alias
        ]);

        $this->assertInstanceOf(Index::class, $result);
        // The name should be derived from 'SomeCustomIndex' -> 'some_custom_index'
        $this->assertSame('some_custom_index', $result->getName());
    }
}
