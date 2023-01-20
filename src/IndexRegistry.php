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

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Locator\LocatorInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Utility\Inflector;
use RuntimeException;

/**
 * Factory/Registry class for Index objects.
 *
 * Handles ensuring only one instance of each index is
 * created and that the correct connection is injected in.
 *
 * Provides an interface similar to Cake\ORM\TableRegistry.
 */
class IndexRegistry implements LocatorInterface
{
    /**
     * The map of instances in the registry.
     *
     * @var array
     */
    protected static array $instances = [];

    /**
     * List of options by alias passed to get.
     *
     * @var array
     */
    protected static array $options = [];

    /**
     * Fallback class to use
     *
     * @var string
     */
    protected static string $fallbackClassName = Index::class;

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
        static::$fallbackClassName = $className;
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
        if (isset(static::$instances[$alias])) {
            if (!empty($options) && static::$options[$alias] !== $options) {
                throw new RuntimeException(sprintf(
                    'You cannot configure "%s", it already exists in the registry.',
                    $alias
                ));
            }

            return static::$instances[$alias];
        }

        static::$options[$alias] = $options;
        [, $classAlias] = pluginSplit($alias);
        $options += ['name' => Inflector::underscore($classAlias)];

        if (empty($options['className'])) {
            $options['className'] = Inflector::camelize($alias);
        }
        $className = App::className($options['className'], 'Model/Index', 'Index');
        if ($className) {
            $options['className'] = $className;
        } else {
            if (!isset($options['name']) && strpos($options['className'], '\\') === false) {
                [, $name] = pluginSplit($options['className']);
                $options['name'] = Inflector::underscore($name);
            }
            $options['className'] = static::$fallbackClassName;
        }

        if (empty($options['connection'])) {
            $connectionName = $options['className']::defaultConnectionName();
            $options['connection'] = ConnectionManager::get($connectionName);
        }
        $options['registryAlias'] = $alias;
        static::$instances[$alias] = new $options['className']($options);

        return static::$instances[$alias];
    }

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public function exists(string $alias): bool
    {
        return isset(static::$instances[$alias]);
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
        return static::$instances[$alias] = $repository;
    }

    /**
     * Clears the registry of configuration and instances.
     *
     * @return void
     */
    public function clear(): void
    {
        static::$instances = [];
    }

    /**
     * Removes an instance from the registry.
     *
     * @param string $alias The alias to remove.
     * @return void
     */
    public function remove(string $alias): void
    {
        unset(static::$instances[$alias]);
    }
}
