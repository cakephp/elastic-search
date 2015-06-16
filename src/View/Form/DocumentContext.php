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
namespace Cake\ElasticSearch\View\Form;

use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Type;
use Cake\Collection\Collection;
use Cake\Core\App;
use Cake\Network\Request;
use Cake\Utility\Inflector;
use Cake\View\Form\ContextInterface;
use Traversable;
use RuntimeException;

/**
 * Provides a context provider for Elasticsearch documents.
 */
class DocumentContext implements ContextInterface
{
    /**
     * The request object.
     *
     * @var \Cake\Network\Request
     */
    protected $_request;

    /**
     * The context data
     *
     * @var array
     */
    protected $_context;

    /**
     * The name of the top level entity/type object.
     *
     * @var string
     */
    protected $_rootName;

    /**
     * Boolean to track whether or not the entity is a
     * collection.
     *
     * @var bool
     */
    protected $_isCollection = false;

    /**
     * A dictionary of types
     *
     * @var array
     */
    protected $_types = [];

    /**
     * Constructor.
     *
     * @param \Cake\Network\Request $request The request object.
     * @param array $context Context info.
     */
    public function __construct(Request $request, array $context)
    {
        $this->_request = $request;
        $context += [
            'entity' => null,
            'type' => null,
            'validator' => [],
        ];
        $this->_context = $context;
        $this->_prepare();
    }

    /**
     * Prepare some additional data from the context.
     *
     * If the table option was provided to the constructor and it
     * was a string, TypeRegistry will be used to get the correct table instance.
     *
     * If an object is provided as the type option, it will be used as is.
     *
     * If no type option is provided, the type name will be derived based on
     * naming conventions. This inference will work with a number of common objects
     * like arrays, Collection objects and ResultSets.
     *
     * @return void
     * @throws \RuntimeException When a table object cannot be located/inferred.
     */
    protected function _prepare()
    {
        $type = $this->_context['type'];
        $entity = $this->_context['entity'];
        if (empty($type)) {
            if (is_array($entity) || $entity instanceof Traversable) {
                $entity = (new Collection($entity))->first();
            }
            $isDocument = $entity instanceof Document;

            if ($isDocument) {
                $type = $entity->source();
            }
            if (!$type && $isDocument && get_class($entity) !== 'Cake\ElasticSearch\Document') {
                list(, $entityClass) = namespaceSplit(get_class($entity));
                $type = Inflector::pluralize($entityClass);
            }
        }
        if (is_string($type)) {
            $typeClass = App::className($type, 'Model\Type', 'Type');
            if ($typeClass) {
                $type = new $type();
            } else {
                $type = new Type(['name' => $type]);
            }
        }

        if (!is_object($type)) {
            throw new RuntimeException(
                'Unable to find type class for current entity'
            );
        }
        $this->_isCollection = (
            is_array($entity) ||
            $entity instanceof Traversable
        );
        $alias = $this->_rootName = $type->name();
        $this->_types[$alias] = $type;
    }

    /**
     * {@inheritDoc}
     */
    public function primaryKey()
    {
        return ['id'];
    }

    /**
     * {@inheritDoc}
     */
    public function isPrimaryKey($field)
    {
        $parts = explode('.', $field);
        return array_pop($parts) === 'id';
    }

    /**
     * {@inheritDoc}
     */
    public function isCreate()
    {
        $entity = $this->_context['entity'];
        if (is_array($entity) || $entity instanceof Traversable) {
            $entity = (new Collection($entity))->first();
        }
        if ($entity instanceof Document) {
            return $entity->isNew() !== false;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function val($field)
    {
        $val = $this->_request->data($field);
        if ($val !== null) {
            return $val;
        }
        if (empty($this->_context['entity'])) {
            return null;
        }
        $parts = explode('.', $field);
        $entity = $this->entity($parts);
        if ($entity instanceof Document) {
            return $entity->get(array_pop($parts));
        }
    }

    protected function entity($path)
    {
        if ($path === null) {
            return $this->_context['entity'];
        }

        $oneElement = count($path) === 1;
        if ($oneElement && $this->_isCollection) {
            return false;
        }
        $entity = $this->_context['entity'];
        if ($oneElement) {
            return $entity;
        }

        if ($path[0] === $this->_rootName) {
            $path = array_slice($path, 1);
        }

        $len = count($path);
        $last = $len - 1;
        for ($i = 0; $i < $len; $i++) {
            $prop = $path[$i];
            $next = $this->getProp($entity, $prop);
            $isLast = ($i === $last);

            if (!$isLast && $next === null && $prop !== '_ids') {
                // TODO get embedded docs here.
                return false;
                // $table = $this->_getTable($path);
                // return $table->newEntity();
            }

            $isTraversable = (
                is_array($next) ||
                $next instanceof Traversable ||
                $next instanceof Document
            );
            if ($isLast || !$isTraversable) {
                return $entity;
            }
            $entity = $next;
        }
        throw new RuntimeException(sprintf(
            'Unable to fetch property "%s"',
            implode(".", $path)
        ));
    }

    /**
     * Read property values or traverse arrays/iterators.
     *
     * @param mixed $target The entity/array/collection to fetch $field from.
     * @param string $field The next field to fetch.
     * @return mixed
     */
    protected function getProp($target, $field)
    {
        if (is_array($target) && isset($target[$field])) {
            return $target[$field];
        }
        if ($target instanceof Document) {
            return $target->get($field);
        }
        if ($target instanceof Traversable) {
            foreach ($target as $i => $val) {
                if ($i == $field) {
                    return $val;
                }
            }
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isRequired($field)
    {

    }

    /**
     * {@inheritDoc}
     */
    public function fieldNames()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function type($field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function attributes($field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function hasError($field)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function error($field)
    {
    }
}
