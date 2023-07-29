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
 * @since         0.5.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch;

use Cake\Datasource\Locator\LocatorInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Elasticsearch\Datasource\IndexLocator;

/**
 * Factory/Registry class for Index objects.
 *
 * Handles ensuring only one instance of each index is
 * created and that the correct connection is injected in.
 *
 * Provides an interface similar to Cake\ORM\TableRegistry.
 *
 * @deprecated 3.4.3 Statically accesible registry is deprecated. Prefer using `IndexLocator`
 *   alongside the `LocatorTrait` in CakePHP.
 */
class IndexRegistry implements LocatorInterface
{
    /**
     * The locator that the global registy is wrapping.
     *
     * @var \Cake\ElasticSearch\Cake\ElasticSearch\Datasource\IndexLocator
     */
    protected static IndexLocator $locator;

    /**
     * Get the wrapped locator.
     *
     * @return \Cake\ElasticSearch\IndexLocator
     */
    protected static function getLocator(): IndexLocator
    {
        if (!isset(static::$locator)) {
            static::$locator = new IndexLocator();
        }

        return static::$locator;
    }

    /**
     * Set fallback class name.
     *
     * The class that should be used to create a index instance if a concrete
     * class for alias used in `get()` could not be found. Defaults to
     * `Cake\ElasticSearch\Index`.
     *
     * @param string $className Fallback class name
     * @return void
     */
    public static function setFallbackClassName(string $className): void
    {
        static::getLocator()->setFallbackClassName($className);
    }

    /**
     * Get/Create an instance from the registry.
     *
     * When getting an instance, if it does not already exist,
     * a new instance will be created using the provide alias, and options.
     *
     * @param string $alias The name of the alias to get.
     * @param array $options Configuration options for the type constructor.
     * @return \Cake\ElasticSearch\Index
     */
    public function get(string $alias, array $options = []): Index
    {
        return static::getLocator()->get($alias, $options);
    }

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public function exists(string $alias): bool
    {
        return static::getLocator()->exists($alias);
    }

    /**
     * Set an instance.
     *
     * @param string $alias The alias to set.
     * @param \Cake\Datasource\RepositoryInterface $repository The type to set.
     * @return \Cake\ElasticSearch\Index
     * @psalm-return \Cake\Datasource\RepositoryInterface
     */
    public function set(string $alias, RepositoryInterface $repository): RepositoryInterface
    {
        return static::getLocator()->set($alias, $repository);
    }

    /**
     * Clears the registry of configuration and instances.
     *
     * @return void
     */
    public function clear(): void
    {
        static::getLocator()->clear();
    }

    /**
     * Removes an instance from the registry.
     *
     * @param string $alias The alias to remove.
     * @return void
     */
    public function remove(string $alias): void
    {
        static::getLocator()->remove($alias);
    }
}
