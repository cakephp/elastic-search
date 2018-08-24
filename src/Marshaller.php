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

use Cake\Collection\Collection;
use Cake\Datasource\EntityInterface;
use Cake\ElasticSearch\Association\Embedded;
use RuntimeException;

/**
 * Contains logic to convert array data into document objects.
 *
 * Useful when converting request data into documents.
 */
class Marshaller
{
    /**
     * Index instance this marshaller is for.
     *
     * @var \Cake\ElasticSearch\Index
     */
    protected $index;

    /**
     * Constructor
     *
     * @param \Cake\ElasticSearch\Index $index The index instance this marshaller is for.
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * Hydrate a single document.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     * * associated: A list of embedded documents you want to marshal.
     *
     * @param array $data The data to hydrate.
     * @param array $options List of options
     * @return \Cake\ElasticSearch\Document;
     */
    public function one(array $data, array $options = [])
    {
        $entityClass = $this->index->entityClass();
        $entity = $this->createAndHydrate($entityClass, $data, $options);
        $entity->setSource($this->index->getRegistryAlias());

        return $entity;
    }

    /**
     * Creates and Hydrates Document whilst honouring accessibleFields etc
     *
     * @param string $class      Class name of Document to create
     * @param array  $data       The data to hydrate with
     * @param array  $options    Options to control the hydration
     * @param string $indexClass Index class to get embeds from (for nesting)
     *
     * @return Document
     */
    protected function createAndHydrate($class, array $data, array $options = [], $indexClass = null)
    {
        $entity = new $class();

        $options += ['associated' => []];

        list($data, $options) = $this->_prepareDataAndOptions($data, $options);

        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->setAccess($key, $value);
            }
        }
        $errors = $this->_validate($data, $options, true);
        $entity->setErrors($errors);
        foreach (array_keys($errors) as $badKey) {
            unset($data[$badKey]);
        }

        if ($indexClass === null) {
            $embeds = $this->index->embedded();
        } else {
            $index = IndexRegistry::get($indexClass);
            $embeds = $index->embedded();
        }

        foreach ($embeds as $embed) {
            $property = $embed->property();
            $alias = $embed->getAlias();
            if (isset($data[$property])) {
                if (isset($options['associated'][$alias])) {
                    $entity->set($property, $this->newNested($embed, $data[$property], $options['associated'][$alias]));
                    unset($data[$property]);
                } elseif (in_array($alias, $options['associated'])) {
                    $entity->set($property, $this->newNested($embed, $data[$property]));
                    unset($data[$property]);
                }
            }
        }

        if (!isset($options['fieldList'])) {
            $entity->set($data);
        } else {
            foreach ((array)$options['fieldList'] as $field) {
                if (array_key_exists($field, $data)) {
                    $entity->set($field, $data[$field]);
                }
            }
        }

        return $entity;
    }

    /**
     * Marshal an embedded document.
     *
     * @param \Cake\ElasticSearch\Association\Embedded $embed   The embed definition.
     * @param array                                    $data    The data to marshal
     * @param array                                    $options The options to pass on
     *
     * @return array|\Cake\ElasticSearch\Document Either a document or an array of documents.
     */
    protected function newNested(Embedded $embed, array $data, array $options = [])
    {
        $class = $embed->entityClass();
        if ($embed->type() === Embedded::ONE_TO_ONE) {
            return $this->createAndHydrate($class, $data, $options, $embed->indexClass());
        }

        if ($embed->type() === Embedded::ONE_TO_MANY) {
            $children = [];
            foreach ($data as $row) {
                if (is_array($row)) {
                    $children[] = $this->createAndHydrate($class, $row, $options, $embed->indexClass());
                }
            }

            return $children;
        }
    }

    /**
     * Merge an embedded document.
     *
     * @param \Cake\ElasticSearch\Association\Embedded $embed The embed definition.
     * @param \Cake\ElasticSearch\Document|array $existing The existing entity or entities.
     * @param array $data The data to marshal
     * @return array|\Cake\ElasticSearch\Document Either a document or an array of documents.
     */
    protected function mergeNested(Embedded $embed, $existing, array $data)
    {
        $class = $embed->entityClass();
        if ($embed->type() === Embedded::ONE_TO_ONE) {
            if (!($existing instanceof EntityInterface)) {
                $existing = new $class();
            }
            $existing->set($data);

            return $existing;
        }

        if ($embed->type() === Embedded::ONE_TO_MANY) {
            if (!is_array($existing)) {
                $existing = [];
            }
            foreach ($existing as $i => $row) {
                if (isset($data[$i])) {
                    $row->set($data[$i]);
                }
                unset($data[$i]);
            }
            foreach ($data as $row) {
                if (is_array($row)) {
                    $new = new $class();
                    $new->set($row);
                    $existing[] = $new;
                }
            }

            return $existing;
        }
    }

    /**
     * Hydrate a collection of entities.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param array $data A list of entity data you want converted into objects.
     * @param array $options Options
     * @return array An array of hydrated entities
     */
    public function many(array $data, array $options = [])
    {
        $output = [];
        foreach ($data as $record) {
            $output[] = $this->one($record, $options);
        }

        return $output;
    }

    /**
     * Merges `$data` into `$document`.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present
     *   the accessible fields list in the entity will be used.
     * * associated: A list of embedded documents you want to marshal.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array $options List of options.
     * @return \Cake\Datasource\EntityInterface
     */
    public function merge(EntityInterface $entity, array $data, array $options = [])
    {
        $options += ['associated' => []];
        list($data, $options) = $this->_prepareDataAndOptions($data, $options);
        $errors = $this->_validate($data, $options, $entity->isNew());
        $entity->setErrors($errors);

        foreach (array_keys($errors) as $badKey) {
            unset($data[$badKey]);
        }

        foreach ($this->index->embedded() as $embed) {
            $property = $embed->property();
            if (in_array($embed->getAlias(), $options['associated']) &&
                isset($data[$property])
            ) {
                $data[$property] = $this->mergeNested($embed, $entity->{$property}, $data[$property]);
            }
        }

        if (!isset($options['fieldList'])) {
            $entity->set($data);

            return $entity;
        }

        foreach ((array)$options['fieldList'] as $field) {
            if (array_key_exists($field, $data)) {
                $entity->set($field, $data[$field]);
            }
        }

        return $entity;
    }

    /**
     * Update a collection of entities.
     *
     * Merges each of the elements from `$data` into each of the entities in `$entities`.
     *
     * Records in `$data` are matched against the entities using the id field.
     * Entries in `$entities` that cannot be matched to any record in
     * `$data` will be discarded. Records in `$data` that could not be matched will
     * be marshalled as a new entity.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     *
     * @param array $entities An array of Elasticsearch entities
     * @param array $data A list of entity data you want converted into objects.
     * @param array $options Options
     * @return array An array of merged entities
     */
    public function mergeMany(array $entities, array $data, array $options = [])
    {
        $indexed = (new Collection($data))
            ->groupBy('id')
            ->map(function ($element, $key) {
                return $key === '' ? $element : $element[0];
            })
            ->toArray();

        $new = isset($indexed[null]) ? $indexed[null] : [];
        unset($indexed[null]);

        $output = [];
        foreach ($entities as $record) {
            if (!($record instanceof EntityInterface)) {
                continue;
            }
            $id = $record->id;
            if (!isset($indexed[$id])) {
                continue;
            }
            $output[] = $this->merge($record, $indexed[$id], $options);
            unset($indexed[$id]);
        }
        $new = array_merge($indexed, $new);
        foreach ($new as $newRecord) {
            $output[] = $this->one($newRecord, $options);
        }

        return $output;
    }

    /**
     * Returns the validation errors for a data set based on the passed options
     *
     * @param array $data The data to validate.
     * @param array $options The options passed to this marshaller.
     * @param bool $isNew Whether it is a new entity or one to be updated.
     * @return array The list of validation errors.
     * @throws \RuntimeException If no validator can be created.
     */
    protected function _validate($data, $options, $isNew)
    {
        if (!$options['validate']) {
            return [];
        }

        if ($options['validate'] === true) {
            $options['validate'] = $this->index->getValidator('default');
        }
        if (is_string($options['validate'])) {
            $options['validate'] = $this->index->getValidator($options['validate']);
        }
        if (!is_object($options['validate'])) {
            throw new RuntimeException(
                sprintf('validate must be a boolean, a string or an object. Got %s.', gettype($options['validate']))
            );
        }

        return $options['validate']->errors($data, $isNew);
    }

    /**
     * Returns data and options prepared to validate and marshall.
     *
     * @param array $data The data to prepare.
     * @param array $options The options passed to this marshaller.
     * @return array An array containing prepared data and options.
     */
    protected function _prepareDataAndOptions($data, $options)
    {
        $options += ['validate' => true];
        $data = new \ArrayObject($data);
        $options = new \ArrayObject($options);
        $this->index->dispatchEvent('Model.beforeMarshal', compact('data', 'options'));

        return [(array)$data, (array)$options];
    }
}
