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
use Cake\ElasticSearch\Type;

/**
 * Contains logic to convert array data into document objects.
 *
 * Useful when converting request data into documents.
 */
class Marshaller
{
    protected $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    /**
     * Hydrate a single document.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param array $data The data to hydrate.
     * @param array $options List of options
     * @return \Cake\ElasticSearch\Document;
     */
    public function one(array $data, array $options = [])
    {
        $entityClass = $this->type->entityClass();
        $entity = new $entityClass();
        $entity->source($this->type->name());

        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->accessible($key, $value);
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
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array $options List of options.
     * @return \Cake\Datasource\EntityInterface
     */
    public function merge(EntityInterface $entity, array $data, array $options = [])
    {
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
     * @param array $data A list of entity data you want converted into objects.
     * @param array $options Options
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
}
