<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\Association;

use Cake\Core\App;
use Cake\ElasticSearch\Document;
use Cake\ElasticSearch\Exception\MissingDocumentException;
use Cake\ElasticSearch\Index;
use Cake\Utility\Inflector;

/**
 * Represents an embedded document.
 *
 * Subclassed for the various kinds of embedded document types.
 */
abstract class Embedded
{
    /**
     * Type name for a single embedded document.
     *
     * @var string
     */
    public const ONE_TO_ONE = 'oneToOne';

    /**
     * Type name for many embedded documents.
     *
     * @var string
     */
    public const ONE_TO_MANY = 'oneToMany';

    /**
     * The alias this association uses.
     *
     * @var string
     */
    protected string $alias;

    /**
     * The class to use for the embeded document.
     *
     * @var string
     */
    protected string $entityClass;

    /**
     * The property the embedded document is located under.
     *
     * @var string
     */
    protected string $property;

    /**
     * The index class this embed is linked to
     *
     * @var string
     */
    protected string $indexClass;

    /**
     * Constructor
     *
     * @param string $alias The alias/name for the embedded document.
     * @param array $options The options for the embedded document.
     */
    public function __construct(string $alias, array $options = [])
    {
        $this->alias = $alias;
        $properties = [
            'entityClass' => 'setEntityClass',
            'property' => 'setProperty',
            'indexClass' => 'setIndexClass',
        ];
        $options += [
            'entityClass' => $alias,
        ];
        foreach ($properties as $prop => $method) {
            if (isset($options[$prop])) {
                $this->{$method}($options[$prop]);
            }
        }
    }

    /**
     * Get the property this embed is attached to.
     *
     * @return string The property name.
     */
    public function getProperty(): string
    {
        if (!isset($this->property)) {
            $this->property = Inflector::underscore($this->alias);
        }

        return $this->property;
    }

    /**
     * Set the property this embed is attached to.
     *
     * @param string|null $name The property name to set.
     * @return $this
     */
    public function setProperty(?string $name = null)
    {
        $this->property = $name;

        return $this;
    }

    /**
     * Get/set the property this embed is attached to.
     *
     * @deprecated 3.2.0 Use setProperty()/getProperty() instead.
     * @param string|null $name The property name to set.
     * @return string The property name.
     */
    public function property(?string $name = null): string
    {
        deprecationWarning(
            '3.3.0',
            static::class . '::property() is deprecated. ' .
            'Use setProperty()/getProperty() instead.'
        );

        if ($name !== null) {
            $this->setProperty($name);
        }

        return $this->getProperty();
    }

    /**
     * Get the entity/document class used for this embed.
     *
     * @return string The class name.
     */
    public function getEntityClass(): string
    {
        if (!isset($this->entityClass)) {
            $default = Document::class;
            $self = static::class;
            $parts = explode('\\', $self);

            if ($self === self::class || count($parts) < 3) {
                return $this->entityClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -5));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Document\\' . $alias;
            if (!class_exists($name)) {
                return $this->entityClass = $default;
            }

            $class = App::className($name, 'Model/Document');
            if (!$class) {
                throw new MissingDocumentException([$name]);
            }
            $this->entityClass = $class;
        }

        return $this->entityClass;
    }

    /**
     * Sets the entity/document class used for this embed.
     *
     * @param string $name The name of the class to use
     * @return $this
     */
    public function setEntityClass(string $name)
    {
        $class = App::className($name, 'Model/Document');
        $this->entityClass = $class ?? Document::class;

        return $this;
    }

    /**
     * Get/set the entity/document class used for this embed.
     *
     * @deprecated 3.2.0 Use setEntityClass()/getEntityClass() instead.
     * @param string|null $name The class name to set.
     * @return string The class name.
     */
    public function entityClass(?string $name = null): string
    {
        deprecationWarning(
            '3.3',
            static::class . '::entityClass() is deprecated. ' .
            'Use setEntityClass()/getEntityClass() instead.'
        );

        if ($name !== null) {
            $this->setEntityClass($name);
        }

        return $this->getEntityClass();
    }

    /**
     * Get the index class used for this embed.
     *
     * @return string The class name.
     */
    public function getIndexClass(): string
    {
        if (!isset($this->indexClass)) {
            $alias = Inflector::pluralize($this->alias);
            $class = App::className($alias . 'Index', 'Model/Index');

            if ($class) {
                return $this->indexClass = $class;
            }

            $this->indexClass = Index::class;
        }

        return $this->indexClass;
    }

    /**
     * Set the index class used for this embed.
     *
     * @param \Cake\ElasticSearch\Index|string|null $name The class name to set.
     * @return $this
     */
    public function setIndexClass(string|Index|null $name)
    {
        if ($name instanceof Index) {
            $this->indexClass = get_class($name);
        } elseif (is_string($name)) {
            $class = App::className($name, 'Model/Index');
            $this->indexClass = $class;
        }

        return $this;
    }

    /**
     * Get/set the index class used for this embed.
     *
     * @deprecated 3.2.0 Use setIndexClass()/getIndexClass() instead.
     * @param \Cake\ElasticSearch\Index|string|null $name The class name to set.
     * @return string The class name.
     */
    public function indexClass(string|Index|null $name = null): string
    {
        deprecationWarning(
            '3.3.0',
            static::class . '::indexClass() is deprecated. ' .
            'Use setIndexClass()/getIndexClass() instead.'
        );

        if ($name !== null) {
            $this->setIndexClass($name);
        }

        return $this->getIndexClass();
    }

    /**
     * Get the alias for this embed.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Hydrate instance(s) from the parent documents data.
     *
     * @param array $data The data to use in the embedded document.
     * @param array $options The options to use in the new document.
     * @return \Cake\ElasticSearch\Document|array
     */
    abstract public function hydrate(array $data, array $options): Document|array;

    /**
     * Get the type of association this is.
     *
     * Returns one of the association type constants.
     *
     * @return string
     */
    abstract public function type(): string;
}
