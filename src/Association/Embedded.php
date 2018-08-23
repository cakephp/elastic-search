<?php
namespace Cake\ElasticSearch\Association;

use Cake\Core\App;
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
    const ONE_TO_ONE = 'oneToOne';

    /**
     * Type name for many embedded documents.
     *
     * @var string
     */
    const ONE_TO_MANY = 'oneToMany';

    /**
     * The alias this association uses.
     *
     * @var string
     */
    protected $alias;

    /**
     * The class to use for the embeded document.
     *
     * @var string
     */
    protected $entityClass;

    /**
     * The property the embedded document is located under.
     *
     * @var string
     */
    protected $property;

    /**
     * The index class this embed is linked to
     *
     * @var string
     */
    protected $indexClass;

    /**
     * Constructor
     *
     * @param string $alias The alias/name for the embedded document.
     * @param array $options The options for the embedded document.
     */
    public function __construct($alias, $options = [])
    {
        $this->alias = $alias;
        $properties = [
            'entityClass',
            'property',
            'indexClass'
        ];
        $options += [
            'entityClass' => $alias
        ];
        foreach ($properties as $prop) {
            if (isset($options[$prop])) {
                $this->{$prop}($options[$prop]);
            }
        }
    }

    /**
     * Get/set the property this embed is attached to.
     *
     * @param string|null $name The property name to set.
     * @return string The property name.
     */
    public function property($name = null)
    {
        if ($name === null) {
            if (!$this->property) {
                $this->property = Inflector::underscore($this->alias);
            }

            return $this->property;
        }
        $this->property = $name;
    }

    /**
     * Get/set the entity/document class used for this embed.
     *
     * @param string|null $name The class name to set.
     * @return string The class name.
     */
    public function entityClass($name = null)
    {
        if ($name === null && !$this->entityClass) {
            $default = '\Cake\ElasticSearch\Document';
            $self = get_called_class();
            $parts = explode('\\', $self);

            if ($self === __CLASS__ || count($parts) < 3) {
                return $this->entityClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -5));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Document\\' . $alias;
            if (!class_exists($name)) {
                return $this->entityClass = $default;
            }
        }

        if ($name !== null) {
            $class = App::className($name, 'Model/Document');
            $this->entityClass = $class;
        }

        return $this->entityClass;
    }

    /**
     * Get/set the index class used for this embed.
     *
     * @param string|null|Index $name The class name to set.
     *
     * @return string The class name.
     */
    public function indexClass($name = null)
    {
        if ($name === null && !$this->indexClass) {
            $alias = Inflector::pluralize($this->alias);
            $class = App::className($alias . 'Index', 'Model/Index');

            if ($class) {
                return $this->indexClass = $class;
            } else {
                return $this->indexClass = '\Cake\ElasticSearch\Index';
            }
        }

        if ($name !== null) {
            if ($name instanceof Index) {
                $this->indexClass = get_class($name);
            } elseif (is_string($name)) {
                $class = App::className($name, 'Model/Index');
                $this->indexClass = $class;
            }
        }

        return $this->indexClass;
    }

    /**
     * Get the alias for this embed.
     *
     * @return string
     */
    public function alias()
    {
        deprecationWarning(
            'Embedded::alias() is deprecated. ' .
            'Use Embedded::getAlias() instead.'
        );

        return $this->alias;
    }

    /**
     * Get the alias for this embed.
     *
     * @return string
     */
    public function getAlias()
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
    abstract public function hydrate(array $data, $options);

    /**
     * Get the type of association this is.
     *
     * Returns one of the association type constants.
     *
     * @return string
     */
    abstract public function type();
}
