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
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch;

use ArrayObject;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\RulesAwareTrait;
use Cake\ElasticSearch\Association\EmbedOne;
use Cake\ElasticSearch\Association\EmbedMany;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\MappingSchema;
use Cake\ElasticSearch\Marshaller;
use Cake\ElasticSearch\Query;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventManager;
use Cake\Event\EventManagerTrait;
use Cake\ORM\RulesChecker;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareTrait;
use Elastica\Document as ElasticaDocument;
use InvalidArgumentException;

/**
 * Base class for mapping types in indexes.
 *
 * A type in elastic search is approximately equivalent to a table or collection
 * in a relational datastore. While an index can have multiple types, this ODM maps
 * each type in an index maps to a class.
 */
class Type implements RepositoryInterface, EventDispatcherInterface
{
    use EventManagerTrait;
    use ValidatorAwareTrait;
    use RulesAwareTrait;

    /**
     * Default validator name.
     *
     * @var string
     */
    const DEFAULT_VALIDATOR = 'default';

    /**
     * Validator provider name.
     *
     * @var string
     */
    const VALIDATOR_PROVIDER_NAME = 'collection';

    /**
     * Connection instance
     *
     * @var Cake\ElasticSearch\Connection
     */
    protected $_connection;

    /**
     * The name of the Elastic Search type this class represents
     *
     * @var string
     */
    protected $_name;

    /**
     * The name of the class that represent a single document for this type
     *
     * @var string
     */
    protected $_documentClass;

    /**
     * Collection of Embedded sub documents this type has.
     *
     * @var array
     */
    protected $embeds = [];

    /**
     * The mapping schema for this type.
     *
     * @var \Cake\ElasticSearch\Datasource\MappingSchema
     */
    protected $schema;

    /**
     * Constructor
     *
     * ### Options
     *
     * - `connection` The Elastica instance.
     * - `name` The name of the type. If this isn't set the name will be inferred from the class name.
     * - `eventManager` Used to inject a specific eventmanager.
     *
     * At the end of the constructor the `Model.initialize` event will be triggered.
     *
     * @param array $config The configuration options, see above.
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['connection'])) {
            $this->connection($config['connection']);
        }

        if (!empty($config['name'])) {
            $this->name($config['name']);
        }
        $eventManager = null;
        if (isset($config['eventManager'])) {
            $eventManager = $config['eventManager'];
        }
        $this->_eventManager = $eventManager ?: new EventManager();
        $this->dispatchEvent('Model.initialize');
    }

    /**
     * Mark a property in documents of this type as an embedded sub-document.
     *
     * Embedded documents are converted into instances of the named document type.
     * This allows you to attach entity level behavior to subsections of your documents.
     *
     * @param string $name The name of the property that contains the embedded document.
     * @param array $options The options for the embedded document.
     * @return void
     */
    public function embedOne($name, $options = [])
    {
        $this->embeds[] = new EmbedOne($name, $options);
    }

    /**
     * Mark a property in documents of this type as list of embedded sub-documents.
     *
     * Embedded documents are converted into instances of the named document type.
     * This allows you to attach entity level behavior to subsections of your documents.
     *
     * This method will make a list of embedded documents from the named property.
     *
     * @param string $name The name of the property that contains the embedded document.
     * @param array $options The options for the embedded document.
     * @return void
     */
    public function embedMany($name, $options = [])
    {
        $this->embeds[] = new EmbedMany($name, $options);
    }

    /**
     * Get the list of embedded documents this type has.
     *
     * @return array
     */
    public function embedded()
    {
        return $this->embeds;
    }

    /**
     * Get the event manager for this Table.
     *
     * @return Cake\Event\EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Returns the connection instance or sets a new one
     *
     * @param Cake\ElasticSearch\Connection $conn the new connection instance
     * @return Cake\ElasticSearch\Connection
     */
    public function connection($conn = null)
    {
        if ($conn === null) {
            return $this->_connection;
        }
        return $this->_connection = $conn;
    }

    /**
     * Returns the type name name or sets a new one
     *
     * @param string $name the new type name
     * @return string
     */
    public function name($name = null)
    {
        if ($name !== null) {
            $this->_name = $name;
        }

        if ($this->_name === null) {
            $name = namespaceSplit(get_class($this));
            $name = substr(end($name), 0, -4);
            if (empty($name)) {
                $name = '*';
            }
            $this->_name = Inflector::underscore($name);
        }

        return $this->_name;
    }

    /**
     * Get the alias for this Type.
     *
     * This method is just an alias of name().
     *
     * @return string
     */
    public function alias($alias = null)
    {
        return $this->name();
    }

    /**
     * Creates a new Query for this repository and applies some defaults based on the
     * type of search that was selected.
     *
     * ### Model.beforeFind event
     *
     * Each find() will trigger a `Model.beforeFind` event for all attached
     * listeners. Any listener can set a valid result set using $query
     *
     * @param string $type the type of query to perform
     * @param array $options An array that will be passed to Query::applyOptions
     * @return Cake\ElasticSearch\Query
     */
    public function find($type = 'all', $options = [])
    {
        $query = $this->query();
        return $this->callFinder($type, $query, $options);
    }

    /**
     * Returns the query as passed
     *
     * @param \Cake\ElasticSearch\Query $query
     * @param array $options
     * @return \Cake\ElasticSearch\Query
     */
    public function findAll(Query $query, array $options = [])
    {
        return $query;
    }

    /**
     * Calls a finder method directly and applies it to the passed query,
     * if no query is passed a new one will be created and returned
     *
     * @param string $type name of the finder to be called
     * @param \Cake\ElasticSearch\Query $query The query object to apply the finder options to
     * @param array $args List of options to pass to the finder
     * @return \Cake\ElasticSearch\Query
     * @throws \BadMethodCallException
     */
    public function callFinder($type, Query $query, $options = [])
    {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . ucfirst($type);
        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        throw new \BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }

    /**
     * @{inheritdoc}
     *
     * Any key present in the options array will be translated as a GET argument
     * when getting the documetn by its id. This is often useful whe you need to
     * specify the parent or routing.
     *
     * This method will not trigger the Model.beforeFind callback as it does not use
     * queries for the search, but a faster key lookup to the search index.
     *
     * @throws \Elastica\Exception\NotFoundException if no document exist with such id
     */
    public function get($primaryKey, $options = [])
    {
        $type = $this->connection()->getIndex()->getType($this->name());
        $result = $type->getDocument($primaryKey, $options);
        $class = $this->entityClass();

        $options = [
            'markNew' => false,
            'markClean' => true,
            'useSetters' => false,
            'source' => $this->name(),
        ];
        $data = $result->getData();
        $data['id'] = $result->getId();
        foreach ($this->embedded() as $embed) {
            $prop = $embed->property();
            if (isset($data[$prop])) {
                $data[$prop] = $embed->hydrate($data[$prop], $options);
            }
        }

        return new $class($data, $options);
    }

    /**
     * Creates a new Query instance for this repository
     *
     * @return \Cake\ElasticSearch\Query
     */
    public function query()
    {
        return new Query($this);
    }

    /**
     * Get a marshaller for this Type instance.
     *
     * @return \Cake\ElasticSearch\Marshaller
     */
    public function marshaller()
    {
        return new Marshaller($this);
    }

    /**
     * Update all matching records.
     *
     * Sets the $fields to the provided values based on $conditions.
     * This method will *not* trigger beforeSave/afterSave events. If you need those
     * first load a collection of records and update them.
     *
     * @param array $fields A hash of field => new value.
     * @param array $conditions An array of conditions, similar to those used with find()
     * @return boolean Success Returns true if one or more rows are effected.
     */
    public function updateAll($fields, $conditions)
    {
        throw new \RuntimeException('Not implemented yet');
    }

    /**
     * Delete all matching records.
     *
     * Deletes all records matching the provided conditions.
     *
     * This method will *not* trigger beforeDelete/afterDelete events. If you
     * need those first load a collection of records and delete them.
     *
     * @param array $conditions An array of conditions, similar to those used with find()
     * @return boolean Success Returns true if one or more rows are effected.
     * @see RepositoryInterface::delete()
     */
    public function deleteAll($conditions)
    {
        $query = $this->query();
        $query->where($conditions);
        $q = $query->compileQuery();
        $type = $this->connection()->getIndex()->getType($this->name());
        $response = $type->deleteByQuery($query->compileQuery());
        return $response->isOk();
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param array $conditions list of conditions to pass to the query
     * @return boolean
     */
    public function exists($conditions)
    {
        $query = $this->query();
        if (count($conditions) && isset($conditions['id'])) {
            $query->where(function ($builder) use ($conditions) {
                return $builder->ids((array)$conditions['id']);
            });
        } else {
            $query->where($conditions);
        }
        $type = $this->connection()->getIndex()->getType($this->name());
        return $type->count($query->compileQuery()) > 0;
    }

    /**
     * Persists an entity based on the fields that are marked as dirty and
     * returns the same entity after a successful save or false in case
     * of any error.
     *
     * Triggers the `Model.beforeSave` and `Model.afterSave` events.
     *
     * ## Options
     *
     * - `checkRules` Defaults to true. Check deletion rules before deleting the record.
     *
     * @param \Cake\Datasource\EntityInterface the entity to be saved
     * @param array $options
     * @return \Cake\Datasource\EntityInterface|boolean
     */
    public function save(EntityInterface $entity, $options = [])
    {
        $options += ['checkRules' => true];
        $options = new ArrayObject($options);
        $event = $this->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => $options
        ]);
        if ($event->isStopped()) {
            return $event->result;
        }
        if ($entity->errors()) {
            return false;
        }

        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $type = $this->connection()->getIndex()->getType($this->name());
        $id = $entity->id ?: null;

        $data = $entity->toArray();
        unset($data[$id]);
        $doc = new ElasticaDocument($id, $data);
        $doc->setAutoPopulate(true);

        $result = $type->addDocument($doc);

        $entity->id = $doc->getId();
        $entity->_version = $doc->getVersion();
        $entity->isNew(false);
        $entity->source($this->name());
        $entity->clean();

        $this->dispatchEvent('Model.afterSave', [
            'entity' => $entity,
            'options' => $options
        ]);
        return $entity;
    }

    /**
     * Delete a single entity.
     *
     * Deletes an entity and possibly related associations from the database
     * based on the 'dependent' option used when defining the association.
     *
     * Triggers the `Model.beforeDelete` and `Model.afterDelete` events.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to remove.
     * @param array $options The options fo the delete.
     * @return boolean success
     */
    public function delete(EntityInterface $entity, $options = [])
    {
        if (!$entity->has('id')) {
            $msg = 'Deleting requires an "id" value.';
            throw new InvalidArgumentException($msg);
        }
        $options += ['checkRules' => true];
        $options = new ArrayObject($options);
        $event = $this->dispatchEvent('Model.beforeDelete', [
            'entity' => $entity,
            'options' => $options
        ]);
        if ($event->isStopped()) {
            return $event->result;
        }
        if (!$this->checkRules($entity, RulesChecker::DELETE, $options)) {
            return false;
        }

        $data = $entity->toArray();
        unset($data['id']);

        $doc = new ElasticaDocument($entity->id, $data);

        $type = $this->connection()->getIndex()->getType($this->name());
        $result = $type->deleteDocument($doc);

        $this->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => $options
        ]);
        return $result->isOk();
    }

    /**
     * Create a new entity + associated entities from an array.
     *
     * This is most useful when hydrating request data back into entities.
     * For example, in your controller code:
     *
     * ```
     * $article = $this->Articles->newEntity($this->request->data());
     * ```
     *
     * The hydrated entity will correctly do an insert/update based
     * on the primary key data existing in the database when the entity
     * is saved. Until the entity is saved, it will be a detached record.
     *
     * @param array|null $data The data to build an entity with.
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function newEntity($data = null, array $options = [])
    {
        if ($data === null) {
            $class = $this->entityClass();
            return new $class([], ['source' => $this->name()]);
        }
        return $this->marshaller()->one($data, $options);
    }

    /**
     * Create a list of entities + associated entities from an array.
     *
     * This is most useful when hydrating request data back into entities.
     * For example, in your controller code:
     *
     * ```
     * $articles = $this->Articles->newEntities($this->request->data());
     * ```
     *
     * The hydrated entities can then be iterated and saved.
     *
     * @param array $data The data to build an entity with.
     * @param array $options A list of options for the objects hydration.
     * @return array An array of hydrated records.
     */
    public function newEntities(array $data, array $options = [])
    {
        return $this->marshaller()->many($data, $options);
    }

    /**
     * Returns the class used to hydrate rows for this table or sets
     * a new one
     *
     * @param string $name the name of the class to use
     * @throws \RuntimeException when the entity class cannot be found
     * @return string
     */
    public function entityClass($name = null)
    {
        if ($name === null && !$this->_documentClass) {
            $default = '\Cake\ElasticSearch\Document';
            $self = get_called_class();
            $parts = explode('\\', $self);

            if ($self === __CLASS__ || count($parts) < 3) {
                return $this->_documentClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -4));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Document\\' . $alias;
            if (!class_exists($name)) {
                return $this->_documentClass = $default;
            }
        }

        if ($name !== null) {
            $class = App::classname($name, 'Model/Document');
            $this->_documentClass = $class;
        }

        if (!$this->_documentClass) {
            throw new \RuntimeException(sprintf('Missing document class "%s"', $class));
        }

        return $this->_documentClass;
    }

    /**
     * Merges the passed `$data` into `$entity` respecting the accessible
     * fields configured on the entity. Returns the same entity after being
     * altered.
     *
     * This is most useful when editing an existing entity using request data:
     *
     * ```
     * $article = $this->Articles->patchEntity($article, $this->request->data());
     * ```
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = [])
    {
        $marshaller = $this->marshaller();
        return $marshaller->merge($entity, $data, $options);
    }

    /**
     * Merges each of the elements passed in `$data` into the entities
     * found in `$entities` respecting the accessible fields configured on the entities.
     * Merging is done by matching the primary key in each of the elements in `$data`
     * and `$entities`.
     *
     * This is most useful when editing a list of existing entities using request data:
     *
     * ```
     * $article = $this->Articles->patchEntities($articles, $this->request->data());
     * ```
     *
     * @param array|\Traversable $entities the entities that will get the
     * data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array $options A list of options for the objects hydration.
     * @return array
     */
    public function patchEntities($entities, array $data, array $options = [])
    {
        $marshaller = $this->marshaller();
        return $marshaller->mergeMany($entity, $data, $options);
    }

    /**
     * Get the mapping data from the index type.
     *
     * This will fetch the schema from ElasticSearch the first
     * time this method is called.
     *
     *
     * @return array
     */
    public function schema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }
        $name = $this->name();
        $type = $this->connection()->getIndex()->getType($name);
        $this->schema = new MappingSchema($name, $type->getMapping());
        return $this->schema;
    }

    /**
     * Check whether or not a field exists in the mapping.
     *
     * @param string $field The field to check.
     * @return bool
     */
    public function hasField($field)
    {
        $mapping = $this->schema();
        return $mapping->field($field) !== null;
    }

    /**
     * The default connection name to inject when creating an instance.
     *
     * @return string
     */
    public static function defaultConnectionName()
    {
        return 'elastic';
    }
}
