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
namespace Cake\ElasticSearch;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use RuntimeException;

/**
 * Factory/Registry class for Type objects.
 *
 * Handles ensuring only one instance of each type is
 * created and that the correct connection is injected in.
 *
 * Provides an interface similar to Cake\ORM\TableRegistry.
 */
class TypeRegistry
{
    /**
     * The map of instances in the registry.
     *
     * @var array
     */
    protected static $instances = [];

    /**
     * List of options by alias passed to get.
     *
     * @var array
     */
    protected static $options = [];

    /**
     * Get/Create an instance from the registry.
     *
     * When getting an instance, if it does not already exist,
     * a new instance will be created using the provide alias, and options.
     *
     * @param string $alias The name of the alias to get.
     * @param array $options Configuration options for the type constructor.
     * @return \Cake\ElasticSearch\Type
     */
    public static function get($alias, array $options = [])
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
        list(, $classAlias) = pluginSplit($alias);
        $options = $options + ['name' => Inflector::underscore($classAlias)];

        if (empty($options['className'])) {
            $options['className'] = Inflector::camelize($alias);
        }
        $className = App::className($options['className'], 'Model/Type', 'Type');
        if ($className) {
            $options['className'] = $className;
        } else {
            if (!isset($options['name']) && strpos($options['className'], '\\') === false) {
                list(, $name) = pluginSplit($options['className']);
                $options['name'] = Inflector::underscore($name);
            }
            $options['className'] = 'Cake\ElasticSearch\Type';
        }

        if (empty($options['connection'])) {
            $connectionName = $options['className']::defaultConnectionName();
            $options['connection'] = ConnectionManager::get($connectionName);
        }
        static::$instances[$alias] = new $options['className']($options);

        return static::$instances[$alias];
    }

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public static function exists($alias)
    {
        return isset(static::$instances[$alias]);
    }

    /**
     * Set an instance.
     *
     * @param string $alias The alias to set.
     * @param \Cake\ElasticSearch\Type $object The type to set.
     * @return \Cake\ElasticSearch\Type
     */
    public static function set($alias, Type $object)
    {
        return static::$instances[$alias] = $object;
    }

    /**
     * Clears the registry of configuration and instances.
     *
     * @return void
     */
    public static function clear()
    {
        static::$instances = [];
    }

    /**
     * Removes an instance from the registry.
     *
     * @param string $alias The alias to remove.
     * @return void
     */
    public static function remove($alias)
    {
        unset(static::$instances[$alias]);
    }
}
