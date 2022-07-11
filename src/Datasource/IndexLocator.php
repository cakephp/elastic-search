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
 * @since     3.5.0
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Datasource;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Locator\AbstractLocator;
use Cake\ElasticSearch\Index;
use Cake\Utility\Inflector;

/**
 * Datasource FactoryLocator compatible locater implementation.
 */
class IndexLocator extends AbstractLocator
{
    /**
     * Fallback class to use
     *
     * @var string
     * @psalm-var class-string<\Cake\Elasticsearch\Index>
     */
    protected $fallbackClassName = Index::class;

    /**
     * Whether fallback class should be used if a Index class could not be found.
     *
     * @var bool
     */
    protected $allowFallbackClass = true;

    /**
     * Set fallback class name.
     *
     * The class that should be used to create a table instance if a concrete
     * class for alias used in `get()` could not be found. Defaults to
     * `Cake\Elasticsearch\Index`.
     *
     * @param string $className Fallback class name
     * @return $this
     * @psalm-param class-string<\Cake\Elasticsearch\Index> $className
     */
    public function setFallbackClassName(string $className)
    {
        $this->fallbackClassName = $className;

        return $this;
    }

    /**
     * Set if fallback class should be used.
     *
     * Controls whether a fallback class should be used to create a index
     * instance if a concrete class for alias used in `get()` could not be found.
     *
     * @param bool $allow Flag to enable or disable fallback
     * @return $this
     */
    public function allowFallbackClass(bool $allow)
    {
        $this->allowFallbackClass = $allow;

        return $this;
    }

    protected function createInstance(string $alias, array $options)
    {
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
            $options['className'] = $this->fallbackClassName;
        }

        if (empty($options['connection'])) {
            $connectionName = $options['className']::defaultConnectionName();
            $options['connection'] = ConnectionManager::get($connectionName);
        }
        $options['registryAlias'] = $alias;

        return new $options['className']($options);
    }
}
