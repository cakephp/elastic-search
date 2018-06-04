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
use Cake\Datasource\RulesChecker;
use Cake\ElasticSearch\Association\EmbedMany;
use Cake\ElasticSearch\Association\EmbedOne;
use Cake\ElasticSearch\Datasource\MappingSchema;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareTrait;
use Elastica\Document as ElasticaDocument;
use InvalidArgumentException;

/**
 * Base class for index.
 *
 * A index in elastic search is approximately equivalent to a table or collection
 * in a relational datastore. This ODM maps each index to a class.
 */
class Index implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface
{
    use EventDispatcherTrait;
    use RulesAwareTrait;
    use ValidatorAwareTrait;

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
     * The name of the event dispatched when a validator has been built.
     *
     * @var string
     */
    const BUILD_VALIDATOR_EVENT = 'Model.buildValidator';

    /**
     * Connection instance
     *
     * @var \Cake\ElasticSearch\Datasource\Connection
     */
    protected $_connection;

    /**
     * The name of the Elasticsearch index this class represents
     *
     * @var string
     */
    protected $_name;

    /**
     * The name of the Elasticsearch mapping type which this class represents
     *
     * For default, the mapping type is equal to index name for easy use.
     *
     * @var string
     */
    protected $_type;

    /**
     * Registry key used to create this index object
     *
     * @var string
     */
    protected $_registryAlias;

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
     * - connection: The Elastica instance.
     * - name: The name of the index. If this isn't set the name will be inferred from the class name.
     * - type: The name of type mapping used. If this ins't set, the type will be equal to 'name'.
     * - eventManager: Used to inject a specific eventmanager.
     *
     * At the end of the constructor the `Model.initialize` event will be triggered.
     *
     * @param array $config The configuration options, see above.
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['registryAlias'])) {
            $this->setRegistryAlias($config['registryAlias']);
        }
        if (!empty($config['connection'])) {
            $this->setConnection($config['connection']);
        }
        if (!empty($config['name'])) {
            $this->setName($config['name']);
        }
        if (!empty($config['type'])) {
            $this->setType($config['type']);
        }
        $eventManager = null;
        if (isset($config['eventManager'])) {
            $eventManager = $config['eventManager'];
        }
        $this->_eventManager = $eventManager ?: new EventManager();
        $this->initialize($config);
        $this->_eventManager->on($this);
        $this->dispatchEvent('Model.initialize');
    }

    /**
     * Initialize a table instance. Called after the constructor.
     *
     * You can use this method to define embedded documents,
     * define validation and do any other initialization logic you need.
     *
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->embedMany('Comments');
     *  }
     * ```
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config)
    {
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
     * @return \Cake\Event\EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Sets the connection instance
     *
     * @param Cake\ElasticSearch\Datasource\Connection $conn the new connection instance
     * @return $this
     */
    public function setConnection($conn)
    {
        $this->_connection = $conn;

        return $this;
    }

    /**
     * Returns the connection instance
     *
     * @return \Cake\ElasticSearch\Datasource\Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Returns the connection instance or sets a new one
     *
     * @deprecated Use getConnection() and setConnection() instead
     * @param \Cake\ElasticSearch\Datasource\Connection $conn the new connection instance
     * @return \Cake\ElasticSearch\Datasource\Connection
     */
    public function connection($conn = null)
    {
        deprecationWarning(
            'Index::connection() is deprecated. ' .
            'Use Index::setConnection()/getConnection() instead.'
        );

        if ($conn !== null) {
            return $this->setConnection($conn);
        }

        return $this->getConnection();
    }

    /**
     * Sets the index registry key used to create this table instance.
     *
     * @param string $registryAlias The key used to access this object.
     * @return $this
     */
    public function setRegistryAlias($registryAlias)
    {
        $this->_registryAlias = $registryAlias;

        return $this;
    }

    /**
     * Returns the index registry key used to create this instance.
     *
     * @return string
     */
    public function getRegistryAlias()
    {
        if ($this->_registryAlias === null) {
            $this->_registryAlias = $this->getAlias();
        }

        return $this->_registryAlias;
    }

    /**
     * Sets the index name
     *
     * @param string $name Index name
     * @return $this
     */
    public function setName($name)
    {
        $this->_name = $name;

        return $this;
    }

    /**
     * Returns the index name
     *
     * If this isn't set the name will be inferred from the class name
     *
     * @return string
     */
    public function getName()
    {
        if ($this->_name === null) {
            $name = namespaceSplit(get_class($this));
            $name = substr(end($name), 0, -5);
            $this->_name = Inflector::underscore($name);
        }

        return $this->_name;
    }

    /**
     * Returns the index name or sets a new one
     *
     * @deprecated Use getName() and setName() instead
     * @param string $name the new index name
     * @return string
     */
    public function name($name = null)
    {
        deprecationWarning(
            'Index::name() is deprecated. ' .
            'Use Index::setName()/getName() instead.'
        );

        if ($name !== null) {
            $this->setName($name);
        }

        return $this->getName();
    }

    /**
     * Get the index name, as required by QueryTrait
     *
     * This method is just an alias of name().
     *
     * @return string
     */
    public function getTable()
    {
        return $this->getName();
    }

    /**
     * Get the index name, as required by QueryTrait
     *
     * This method is just an alias of name().
     *
     * @return string
     * @deprecated Use getTable() instead
     */
    public function table()
    {
        deprecationWarning(
            'Index::table() is deprecated. ' .
            'Use Index::getTable() instead.'
        );

        return $this->getName();
    }

    /**
     * Get the alias for this Index.
     *
     * This method is just an alias of name().
     *
     * @deprecated Use getAlias() and setAlias() instead
     * @param string $alias The new index name
     * @return string
     */
    public function alias($alias = null)
    {
        deprecationWarning(
            'Index::table() is deprecated. ' .
            'Use Index::setAlias()/getAlias() instead.'
        );

        return $this->getName($alias);
    }

    /**
     * Sets the index alias.
     *
     * @param string $alias Index alias
     * @return $this
     */
    public function setAlias($alias)
    {
        return $this->setName($alias);
    }

    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->getName();
    }

    /**
     * Sets the mapping type name
     *
     * @param string $type Mapping type name
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * Returns the mapping type name
     *
     * If not defined, use the same as index name
     *
     * @return string
     */
    public function getType()
    {
        if ($this->_type === null) {
            $this->_type = Inflector::singularize($this->getName());
        }

        return $this->_type;
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
     * @return \Cake\ElasticSearch\Query
     */
    public function find($type = 'all', $options = [])
    {
        $query = $this->query();

        return $this->callFinder($type, $query, $options);
    }

    /**
     * Returns the query as passed
     *
     * @param \Cake\ElasticSearch\Query $query An Elasticsearch query object
     * @param array $options An array of options to be used for query logic
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
     * @param array $options List of options to pass to the finder
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
     * when getting the document by its id. This is often useful whe you need to
     * specify the parent or routing.
     *
     * This method will not trigger the Model.beforeFind callback as it does not use
     * queries for the search, but a faster key lookup to the search index.
     *
     * @param string $primaryKey The document's primary key
     * @param array $options An array of options
     * @throws \Elastica\Exception\NotFoundException if no document exist with such id
     * @return \Cake\ElasticSearch\Document A new Elasticsearch document entity
     */
    public function get($primaryKey, $options = [])
    {
        $type = $this->getConnection()->getIndex($this->getName())->getType($this->getType());
        $result = $type->getDocument($primaryKey, $options);
        $class = $this->entityClass();

        $options = [
            'markNew' => false,
            'markClean' => true,
            'useSetters' => false,
            'source' => $this->getRegistryAlias(),
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
     * Get a marshaller for this Index instance.
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
     * @return void
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
     * @return bool Success Returns true if one or more rows are effected.
     * @see RepositoryInterface::delete()
     */
    public function deleteAll($conditions)
    {
        $query = $this->query();
        $query->where($conditions);
        $type = $this->getConnection()->getIndex($this->getName())->getType($this->getType());
        $response = $type->deleteByQuery($query->compileQuery());

        return $response->isOk();
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param array $conditions list of conditions to pass to the query
     * @return bool
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

        return $query->count() > 0;
    }

    /**
     * Persists a list of entities based on the fields that are marked as dirty and
     * returns the same entity after a successful save or false in case
     * of any error.
     * Triggers the `Model.beforeSave` and `Model.afterSave` events.
     * ## Options
     * - `checkRules` Defaults to true. Check deletion rules before deleting the record.
     *
     * @param array $entities An array of entities
     * @param array $options An array of options to be used for the event
     * @return bool
     */
    public function saveMany($entities, $options = [])
    {
        $options += [
            'checkRules' => true,
            'refresh' => false
        ];
        $options = new ArrayObject($options);

        $documents = [];

        foreach ($entities as $key => $entity) {
            if (!$entity instanceof EntityInterface) {
                throw new RuntimeException(sprintf(
                    'Invalid items in the list. Found `%s` but expected `%s`',
                    is_object($entity) ? get_class($entity) : gettype($entity),
                    EntityInterface::class
                ));
            }

            $event = $this->dispatchEvent('Model.beforeSave', [
                'entity' => $entity,
                'options' => $options
            ]);

            if ($event->isStopped() || $entity->getErrors()) {
                return false;
            }

            $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
            if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
                return false;
            }

            $id = $entity->id ?: null;

            $data = $entity->toArray();
            unset($data['id'], $data['_version']);

            $doc = new ElasticaDocument($id, $data);
            $doc->setAutoPopulate(true);

            $documents[$key] = $doc;
        }

        $type = $this->getConnection()->getIndex($this->getName())->getType($this->getType());
        $type->addDocuments($documents);

        if ($options['refresh']) {
            $type->getIndex()->refresh();
        }

        foreach ($documents as $key => $document) {
            $entities[$key]->id = $doc->getId();
            $entities[$key]->_version = $doc->getVersion();
            $entities[$key]->isNew(false);
            $entities[$key]->setSource($this->getRegistryAlias());
            $entities[$key]->clean();

            $this->dispatchEvent('Model.afterSave', [
                'entity' => $entities[$key],
                'options' => $options
            ]);
        }

        return true;
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
     * @param \Cake\Datasource\EntityInterface $entity The entity to be saved
     * @param array $options An array of options to be used for the event
     * @return \Cake\Datasource\EntityInterface|bool
     */
    public function save(EntityInterface $entity, $options = [])
    {
        $options += [
            'checkRules' => true,
            'refresh' => false
        ];
        $options = new ArrayObject($options);
        $event = $this->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => $options
        ]);

        if ($event->isStopped()) {
            return $event->result;
        }

        if ($entity->getErrors()) {
            return false;
        }

        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;

        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $type = $this->getConnection()->getIndex($this->getName())->getType($this->getType());
        $id = $entity->id ?: null;

        $data = $entity->toArray();
        unset($data['id'], $data['_version']);

        $doc = new ElasticaDocument($id, $data);
        $doc->setAutoPopulate(true);

        $type->addDocument($doc);

        if ($options['refresh']) {
            $type->getIndex()->refresh();
        }

        $entity->id = $doc->getId();
        $entity->_version = $doc->getVersion();
        $entity->isNew(false);
        $entity->setSource($this->getRegistryAlias());
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
     * @param array $options The options for the delete.
     * @return bool success
     */
    public function delete(EntityInterface $entity, $options = [])
    {
        if (!$entity->has('id')) {
            $msg = 'Deleting requires an "id" value.';
            throw new InvalidArgumentException($msg);
        }
        $options += [
            'checkRules' => true,
            'refresh' => false
        ];
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

        $type = $this->getConnection()->getIndex($this->getName())->getType($this->getType());
        $result = $type->deleteDocument($doc);

        if ($options['refresh']) {
            $type->getIndex()->refresh();
        }

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

            return new $class([], ['source' => $this->getRegistryAlias()]);
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

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -5));
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

        return $marshaller->mergeMany($entities, $data, $options);
    }

    /**
     * Get the mapping data from the index type.
     *
     * This will fetch the schema from Elasticsearch the first
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
        $index = $this->getName();
        $type = $this->getConnection()->getIndex($index)->getType($this->getType());
        $this->schema = new MappingSchema($this->getType(), $type->getMapping());

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
        return $this->schema()->field($field) !== null;
    }

    /**
     * Get the callbacks this Index is interested in.
     *
     * By implementing the conventional methods a Index class is assumed
     * to be interested in the related event.
     *
     * Override this method if you need to add non-conventional event listeners.
     * Or if you want you table to listen to non-standard events.
     *
     * The conventional method map is:
     *
     * - Model.beforeMarshal => beforeMarshal
     * - Model.beforeFind => beforeFind
     * - Model.beforeSave => beforeSave
     * - Model.afterSave => afterSave
     * - Model.beforeDelete => beforeDelete
     * - Model.afterDelete => afterDelete
     * - Model.beforeRules => beforeRules
     * - Model.afterRules => afterRules
     *
     * @return array
     */
    public function implementedEvents()
    {
        $eventMap = [
            'Model.beforeMarshal' => 'beforeMarshal',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
            'Model.beforeRules' => 'beforeRules',
            'Model.afterRules' => 'afterRules',
        ];
        $events = [];

        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }
            $events[$event] = $method;
        }

        return $events;
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
