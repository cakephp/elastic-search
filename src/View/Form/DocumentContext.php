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

use Cake\Collection\Collection;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\IndexRegistry;
use Cake\Http\ServerRequest;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Form\ContextInterface;
use RuntimeException;
use Traversable;

/**
 * Provides a context provider for Elasticsearch documents.
 */
class DocumentContext implements ContextInterface
{
    /**
     * The request object.
     *
     * @var \Cake\Http\ServerRequest
     */
    protected $_request;

    /**
     * The context data
     *
     * @var array
     */
    protected $_context;

    /**
     * The name of the top level entity/index object.
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
     * Constructor.
     *
     * @param \Cake\Http\ServerRequest $request The request object.
     * @param array $context Context info.
     */
    public function __construct(ServerRequest $request, array $context)
    {
        $this->_request = $request;
        $context += [
            'entity' => null,
            'index' => null,
            'validator' => 'default',
        ];
        $this->_context = $context;
        $this->_prepare();
    }

    /**
     * Prepare some additional data from the context.
     *
     * If the table option was provided to the constructor and it
     * was a string, IndexRegistry will be used to get the correct table instance.
     *
     * If an object is provided as the index option, it will be used as is.
     *
     * If no index option is provided, the index name will be derived based on
     * naming conventions. This inference will work with a number of common objects
     * like arrays, Collection objects and ResultSets.
     *
     * @return void
     * @throws \RuntimeException When a table object cannot be located/inferred.
     */
    protected function _prepare()
    {
        $index = $this->_context['index'];
        $entity = $this->_context['entity'];
        if (empty($index)) {
            if (is_array($entity) || $entity instanceof Traversable) {
                $entity = (new Collection($entity))->first();
            }
            $isDocument = $entity instanceof Document;

            if ($isDocument) {
                $index = $entity->getSource();
            }
            if (!$index && $isDocument && get_class($entity) !== 'Cake\ElasticSearch\Document') {
                list(, $entityClass) = namespaceSplit(get_class($entity));
                $index = Inflector::pluralize($entityClass);
            }
        }
        if (is_string($index)) {
            $index = IndexRegistry::get($index);
        }

        if (!is_object($index)) {
            throw new RuntimeException(
                'Unable to find index class for current entity'
            );
        }
        $this->_isCollection = (
            is_array($entity) ||
            $entity instanceof Traversable
        );
        $this->_rootName = $index->getName();
        $this->_context['index'] = $index;
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
        $val = $this->_request->getData($field);
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

        if ($this->_context['entity'] instanceof Document) {
            return Hash::get($this->_context['entity'], $field);
        }
    }

    /**
     * Get the entity that is closest to $path.
     *
     * @param array $path The to get an entity for.
     * @return \Cake\Datasource\EntityInterface|false The entity or false.
     * @throws \RuntimeException when no entity can be found.
     */
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
                return false;
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
        $parts = explode('.', $field);
        $entity = $this->entity($parts);

        if (!$entity) {
            return false;
        }

        $isNew = true;
        if ($entity instanceof Document) {
            $isNew = $entity->isNew();
        }
        $validator = $this->getValidator();

        $field = array_pop($parts);
        if (!$validator->hasField($field)) {
            return false;
        }

        if ($this->type($field) !== 'boolean') {
            return $validator->isEmptyAllowed($field, $isNew) === false;
        }

        return false;
    }

    /**
     * Get the validator for the current index.
     *
     * @return \Cake\Validation\Validator The validator for the index.
     */
    protected function getValidator()
    {
        return $this->_context['index']->getValidator($this->_context['validator']);
    }

    /**
     * {@inheritDoc}
     */
    public function fieldNames()
    {
        $schema = $this->_context['index']->schema();

        return $schema->fields();
    }

    /**
     * {@inheritDoc}
     */
    public function type($field)
    {
        $schema = $this->_context['index']->schema();

        return $schema->fieldType($field);
    }

    /**
     * {@inheritDoc}
     */
    public function attributes($field)
    {
        return ['length' => null, 'precision' => null];
    }

    /**
     * {@inheritDoc}
     */
    public function hasError($field)
    {
        return $this->error($field) !== [];
    }

    /**
     * {@inheritDoc}
     */
    public function error($field)
    {
        $parts = explode('.', $field);
        $entity = $this->entity($parts);
        $entityErrors = [];
        $errors = [];

        if ($this->_context['entity'] instanceof Document) {
            $entityErrors = $this->_context['entity']->getErrors();
        }

        if ($entity instanceof Document) {
            $errors = $entity->getError(array_pop($parts));
        }

        if (!$errors && $entityErrors && !is_array($entity)) {
            $errors = Hash::extract($entityErrors, $field) ?: [];
        }

        return $errors;
    }
}
