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
 * @since         0.0.1
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch;

use ArrayObject;
use BadMethodCallException;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\RulesAwareTrait;
use Cake\Datasource\RulesChecker;
use Cake\ElasticSearch\Association\EmbedMany;
use Cake\ElasticSearch\Association\EmbedOne;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Datasource\MappingSchema;
use Cake\ElasticSearch\Exception\MissingDocumentException;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareTrait;
use Closure;
use Elastica\Document as ElasticaDocument;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base class for index.
 *
 * A index in elastic search is approximately equivalent to a table or collection
 * in a relational datastore. This ODM maps each index to a class.
 *
 * @implements \Cake\Event\EventDispatcherInterface<\Cake\ElasticSearch\Index>
 */
class Index implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Cake\ElasticSearch\Index>
     */
    use EventDispatcherTrait;
    use RulesAwareTrait;
    use ValidatorAwareTrait;

    /**
     * Default validator name.
     *
     * @var string
     */
    public const DEFAULT_VALIDATOR = 'default';

    /**
     * Validator provider name.
     *
     * @var string
     */
    public const VALIDATOR_PROVIDER_NAME = 'collection';

    /**
     * The name of the event dispatched when a validator has been built.
     *
     * @var string
     */
    public const BUILD_VALIDATOR_EVENT = 'Model.buildValidator';

    /**
     * Connection instance
     *
     * @var \Cake\ElasticSearch\Datasource\Connection
     */
    protected Connection $_connection;

    /**
     * The name of the Elasticsearch index this class represents
     *
     * @var string
     */
    protected string $_name;

    /**
     * Registry key used to create this index object
     *
     * @var string
     */
    protected string $_registryAlias;

    /**
     * The name of the class that represent a single document for this type
     *
     * @var string
     */
    protected string $_documentClass;

    /**
     * Collection of Embedded sub documents this type has.
     *
     * @var array
     */
    protected array $embeds = [];

    /**
     * The mapping schema for this type.
     *
     * @var \Cake\ElasticSearch\Datasource\MappingSchema
     */
    protected MappingSchema $schema;

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
     * Initialize a index instance. Called after the constructor.
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
    public function initialize(array $config): void
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
    public function embedOne(string $name, array $options = []): void
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
    public function embedMany(string $name, array $options = []): void
    {
        $this->embeds[] = new EmbedMany($name, $options);
    }

    /**
     * Get the list of embedded documents this type has.
     *
     * @return array
     */
    public function embedded(): array
    {
        return $this->embeds;
    }

    /**
     * Sets the connection instance
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $conn the new connection instance
     * @return $this
     */
    public function setConnection(Connection $conn)
    {
        $this->_connection = $conn;

        return $this;
    }

    /**
     * Returns the connection instance
     *
     * @return \Cake\ElasticSearch\Datasource\Connection
     */
    public function getConnection(): Connection
    {
        return $this->_connection;
    }

    /**
     * Sets the index registry key used to create this index instance.
     *
     * @param string $registryAlias The key used to access this object.
     * @return $this
     */
    public function setRegistryAlias(string $registryAlias)
    {
        $this->_registryAlias = $registryAlias;

        return $this;
    }

    /**
     * Returns the index registry key used to create this instance.
     *
     * @return string
     */
    public function getRegistryAlias(): string
    {
        if (!isset($this->_registryAlias)) {
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
    public function setName(string $name)
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
    public function getName(): string
    {
        if (!isset($this->_name)) {
            $name = namespaceSplit(static::class);
            $name = substr(end($name), 0, -5);
            $this->_name = Inflector::underscore($name);
        }

        return $this->_name;
    }

    /**
     * Get the index name, as required by QueryTrait
     *
     * This method is just an alias of name().
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->getName();
    }

    /**
     * Sets the index alias.
     *
     * @param string $alias Index alias
     * @return $this
     */
    public function setAlias(string $alias)
    {
        return $this->setName($alias);
    }

    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->getName();
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
    public function find(string $type = 'all', array $options = []): Query
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
    public function findAll(Query $query, array $options = []): Query
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
     * @return \Cake\ElasticSearch\Query<\Cake\ElasticSearch\Document>
     * @throws \BadMethodCallException
     */
    public function callFinder(string $type, Query $query, array $options = []): Query
    {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . ucfirst($type);

        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        throw new BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }

    /**
     * {@inheritDoc}
     *
     * Any key present in the options array will be translated as a GET argument
     * when getting the document by its id. This is often useful whe you need to
     * specify the parent or routing.
     *
     * This method will not trigger the Model.beforeFind callback as it does not use
     * queries for the search, but a faster key lookup to the search index.
     *
     * @param mixed $primaryKey The document's primary key
     * @param array $options An array of options
     * @throws \Elastica\Exception\NotFoundException if no document exist with such id
     * @return \Cake\ElasticSearch\Document A new Elasticsearch document entity
     */
    public function get(mixed $primaryKey, $options = []): EntityInterface
    {
        $esIndex = $this->getConnection()->getIndex($this->getName());
        $result = $esIndex->getDocument($primaryKey, $options);
        $class = $this->getEntityClass();

        $options = [
            'markNew' => false,
            'markClean' => true,
            'useSetters' => false,
            'source' => $this->getRegistryAlias(),
        ];
        $data = $result->getData();
        $data['id'] = $result->getId();
        foreach ($this->embedded() as $embed) {
            $prop = $embed->getProperty();
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
    public function query(): Query
    {
        return new Query($this);
    }

    /**
     * Get a marshaller for this Index instance.
     *
     * @return \Cake\ElasticSearch\Marshaller
     */
    public function marshaller(): Marshaller
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
     * @param \Closure|array|string $fields A hash of field => new value.
     * @param \Closure|array|string|null $conditions An array of conditions, similar to those used with find()
     * @return int
     */
    public function updateAll(Closure|array|string $fields, Closure|array|string|null $conditions): int
    {
        throw new RuntimeException('Not implemented yet');
    }

    /**
     * Delete all matching records.
     *
     * Deletes all records matching the provided conditions.
     *
     * This method will *not* trigger beforeDelete/afterDelete events. If you
     * need those first load a collection of records and delete them.
     *
     * @param \Closure|array|string|null $conditions An array of conditions, similar to those used with find()
     * @return int Success Returns 1 if one or more documents are effected.
     * @see RepositoryInterface::delete()
     */
    public function deleteAll(Closure|array|string|null $conditions): int
    {
        $query = $this->query();
        $query->where($conditions);
        $esIndex = $this->getConnection()->getIndex($this->getName());
        $response = $esIndex->deleteByQuery($query->compileQuery());

        return (int)$response->isOk();
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param \Closure|array|string|null $conditions list of conditions to pass to the query
     * @return bool
     */
    public function exists(Closure|array|string|null $conditions): bool
    {
        $query = $this->query();
        if (count($conditions) && is_array($conditions) && isset($conditions['id'])) {
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
     * - `routing` Defaults to null. If set, this is used as the routing key for storing the document.
     *
     * @param array $entities An array of entities
     * @param array $options An array of options to be used for the event
     * @return bool
     */
    public function saveMany(array $entities, array $options = []): bool
    {
        $options += [
            'checkRules' => true,
            'refresh' => false,
            'routing' => null,
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
                'options' => $options,
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
            if ($options['routing'] !== null) {
                $doc->setRouting($options['routing']);
            }

            $documents[$key] = $doc;
        }

        $esIndex = $this->getConnection()->getIndex($this->getName());
        $esIndex->addDocuments($documents);

        if ($options['refresh']) {
            $esIndex->refresh();
        }

        foreach ($documents as $key => $doc) {
            $entities[$key]->id = $doc->getId();
            $entities[$key]->_version = $doc->getVersion();
            $entities[$key]->setNew(false);
            $entities[$key]->setSource($this->getRegistryAlias());
            $entities[$key]->clean();

            $this->dispatchEvent('Model.afterSave', [
                'entity' => $entities[$key],
                'options' => $options,
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
     * - `routing` Defaults to null. If set, this is used as the routing key for storing the document.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to be saved
     * @param array $options An array of options to be used for the event
     * @return \Cake\Datasource\EntityInterface|false
     */
    public function save(EntityInterface $entity, array $options = []): EntityInterface|false
    {
        $options += [
            'checkRules' => true,
            'refresh' => false,
            'routing' => null,
        ];
        $options = new ArrayObject($options);
        $event = $this->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => $options,
        ]);

        if ($event->isStopped()) {
            return $event->getResult();
        }

        if ($entity->getErrors()) {
            return false;
        }

        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;

        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $esIndex = $this->getConnection()->getIndex($this->getName());
        $id = $entity->id ?: null;

        $data = $entity->toArray();
        unset($data['id'], $data['_version']);

        $doc = new ElasticaDocument($id, $data);
        $doc->setAutoPopulate(true);
        if ($options['routing'] !== null) {
            $doc->setRouting($options['routing']);
        }

        $esIndex->addDocument($doc);

        if ($options['refresh']) {
            $esIndex->refresh();
        }

        $entity->id = $doc->getId();
        $entity->version = $doc->getVersion();
        $entity->setNew(false);
        $entity->setSource($this->getRegistryAlias());
        $entity->clean();

        $this->dispatchEvent('Model.afterSave', [
            'entity' => $entity,
            'options' => $options,
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
    public function delete(EntityInterface $entity, array $options = []): bool
    {
        if (!$entity->has('id')) {
            $msg = 'Deleting requires an "id" value.';
            throw new InvalidArgumentException($msg);
        }
        $options += [
            'checkRules' => true,
            'refresh' => false,
        ];
        $options = new ArrayObject($options);
        $event = $this->dispatchEvent('Model.beforeDelete', [
            'entity' => $entity,
            'options' => $options,
        ]);

        if ($event->isStopped()) {
            return (bool)$event->getResult();
        }

        if (!$this->checkRules($entity, RulesChecker::DELETE, $options)) {
            return false;
        }

        $data = $entity->toArray();
        unset($data['id']);

        $doc = new ElasticaDocument($entity->id, $data);

        $esIndex = $this->getConnection()->getIndex($this->getName());
        $result = $esIndex->deleteById($doc->getId());

        if ($options['refresh']) {
            $esIndex->refresh();
        }

        $this->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => $options,
        ]);

        return $result->isOk();
    }

    /**
     * @inheritDoc
     */
    public function newEmptyEntity(): EntityInterface
    {
        $class = $this->getEntityClass();

        return new $class([], ['source' => $this->getRegistryAlias()]);
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
     * @param array $data The data to build an entity with.
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function newEntity(array $data, array $options = []): EntityInterface
    {
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
     * @param array<string, mixed> $options A list of options for the objects hydration.
     * @return array<\Cake\Datasource\EntityInterface> An array of hydrated records.
     */
    public function newEntities(array $data, array $options = []): array
    {
        return $this->marshaller()->many($data, $options);
    }

    /**
     * Returns the class used to hydrate documents for this index.
     *
     * @return string
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-return class-string<\Cake\ElasticSearch\Document>
     */
    public function getEntityClass(): string
    {
        if (!isset($this->_documentClass)) {
            $default = Document::class;
            $self = static::class;
            $parts = explode('\\', $self);

            if ($self === self::class || count($parts) < 3) {
                return $this->_documentClass = $default;
            }

            $alias = Inflector::classify(Inflector::underscore(substr(array_pop($parts), 0, -5)));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\\Document\\' . $alias;
            if (!class_exists($name)) {
                return $this->_documentClass = $default;
            }
            /** @var class-string<\Cake\ElasticSearch\Document>|null $class */
            $class = App::className($name, 'Model/Document');
            if (!$class) {
                throw new MissingDocumentException([$name]);
            }

            $this->_documentClass = $class;
        }

        return $this->_documentClass;
    }

    /**
     * Sets the class used to hydrate documents for this index.
     *
     * @param string $name The name of the class to use
     * @throws \Cake\ElasticSearch\Exception\MissingDocumentException when the entity class cannot be found
     * @return $this
     */
    public function setEntityClass(string $name)
    {
        $class = App::className($name, 'Model/Document');
        if (!$class) {
            throw new MissingDocumentException([$name]);
        }

        $this->_documentClass = $class;

        return $this;
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
    public function patchEntity(EntityInterface $entity, array $data, array $options = []): EntityInterface
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
     * @param iterable $entities the entities that will get the
     * data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array $options A list of options for the objects hydration.
     * @return array<\Cake\Datasource\EntityInterface>
     */
    public function patchEntities(iterable $entities, array $data, array $options = []): array
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
     * @return \Cake\ElasticSearch\Datasource\MappingSchema
     */
    public function schema(): MappingSchema
    {
        if (isset($this->schema)) {
            return $this->schema;
        }
        $index = $this->getName();
        $esIndex = $this->getConnection()->getIndex($index);
        $this->schema = new MappingSchema($index, $esIndex->getMapping());

        return $this->schema;
    }

    /**
     * Check whether or not a field exists in the mapping.
     *
     * @param string $field The field to check.
     * @return bool
     */
    public function hasField(string $field): bool
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
     * Or if you want your index to listen to non-standard events.
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
     * @return array<string,mixed>
     */
    public function implementedEvents(): array
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
    public static function defaultConnectionName(): string
    {
        return 'elastic';
    }
}
