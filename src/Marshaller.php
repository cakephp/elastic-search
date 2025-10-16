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
use Cake\Collection\Collection;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\InvalidPropertyInterface;
use Cake\ElasticSearch\Association\Embedded;
use Cake\Validation\Validator;
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
     */
    protected Index $index;

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
     * - fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * - accessibleFields: A list of fields to allow or deny in entity accessible fields.
     * - associated: A list of embedded documents you want to marshal.
     *
     * @param array<string, mixed> $data The data to hydrate.
     * @param array<string, mixed> $options List of options
     */
    public function one(array $data, array $options = []): Document
    {
        $options += ['associated' => []];

        [$data, $options] = $this->_prepareDataAndOptions($data, $options);

        $entity = $this->index->newEmptyEntity();
        assert($entity instanceof Document);
        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->setAccess($key, $value);
            }
        }

        $errors = $this->_validate($data, $options, true);
        $entity->setErrors($errors);

        $properties = [];
        foreach (array_keys($errors) as $badKey) {
            if (isset($data[$badKey]) && $entity instanceof InvalidPropertyInterface) {
                $entity->setInvalidField($badKey, $data[$badKey]);
            }

            unset($data[$badKey]);
        }

        $embeds = $this->index->embedded();

        foreach ($embeds as $embed) {
            $property = $embed->getProperty();
            $alias = $embed->getAlias();
            if (isset($data[$property])) {
                if (isset($options['associated'][$alias])) {
                    $properties[$property] = $this->newNested($embed, $data[$property], $options['associated'][$alias]);
                    unset($data[$property]);
                } elseif (in_array($alias, $options['associated'], true)) {
                    $properties[$property] = $this->newNested($embed, $data[$property]);
                    unset($data[$property]);
                }
            }
        }

        if (!isset($options['fieldList'])) {
            $entity->patch($data);
        } else {
            $filteredData = [];
            foreach ((array)$options['fieldList'] as $field) {
                if (array_key_exists($field, $data)) {
                    $filteredData[$field] = $data[$field];
                }
            }

            $entity->patch($filteredData);
        }

        // Set embedded properties
        foreach ($properties as $field => $value) {
            $entity->set($field, $value);
        }

        // Don't flag clean embedded documents as
        // dirty so we don't persist empty records.
        foreach ($properties as $field => $value) {
            if ($value instanceof EntityInterface) {
                $entity->setDirty($field, $value->isDirty());
            }

            if (is_array($value)) {
                foreach ($value as $nestedEntity) {
                    if ($nestedEntity instanceof EntityInterface && !$nestedEntity->isDirty()) {
                        $entity->setDirty($field, false);
                        break;
                    }
                }
            }
        }

        $this->dispatchAfterMarshal($entity, $data, $options);

        return $entity;
    }

    /**
     * Marshal an embedded document.
     *
     * @param \Cake\ElasticSearch\Association\Embedded $embed The embed definition.
     * @param array<string, mixed> $data The data to marshal
     * @param array<string, mixed> $options The options to pass on
     * @return \Cake\ElasticSearch\Document|array<\Cake\ElasticSearch\Document> Either a document or an array of documents.
     */
    protected function newNested(Embedded $embed, array $data, array $options = []): Document|array
    {
        $marshaller = $embed->getIndex()->marshaller();
        if ($embed->type() === Embedded::ONE_TO_ONE) {
            return $marshaller->one($data, $options);
        }

        /** @var array<\Cake\ElasticSearch\Document> */
        return $marshaller->many($data, $options);
    }

    /**
     * Merge an embedded document.
     *
     * @param \Cake\ElasticSearch\Association\Embedded $embed The embed definition.
     * @param \Cake\ElasticSearch\Document|array<\Cake\ElasticSearch\Document>|null $existing The existing entity or entities.
     * @param array<string, mixed> $data The data to marshal
     * @return \Cake\ElasticSearch\Document|array<\Cake\ElasticSearch\Document> Either a document or an array of documents.
     */
    protected function mergeNested(Embedded $embed, Document|array|null $existing, array $data): Document|array
    {
        $index = $embed->getIndex();
        if ($embed->type() === Embedded::ONE_TO_ONE) {
            if (!($existing instanceof Document)) {
                $existing = $index->newEmptyEntity();
                assert($existing instanceof Document);
            }

            $existing->patch($data);

            return $existing;
        } else {
            if (!is_array($existing)) {
                $existing = [];
            }

            foreach ($existing as $i => $row) {
                if (isset($data[$i])) {
                    $row->patch($data[$i]);
                }

                unset($data[$i]);
            }

            foreach ($data as $row) {
                if (is_array($row)) {
                    $new = $index->newEmptyEntity();
                    assert($new instanceof Document);
                    $new->patch($row);
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
     * - fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * - accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param array $data The data to hydrate.
     * @param array<string, mixed> $options List of options
     * @return array<\Cake\Datasource\EntityInterface> An array of hydrated records.
     */
    public function many(array $data, array $options = []): array
    {
        $output = [];
        foreach ($data as $record) {
            if (!is_array($record)) {
                continue;
            }

            $output[] = $this->one($record, $options);
        }

        return $output;
    }

    /**
     * Merges `$data` into `$document`.
     *
     * ### Options:
     *
     * - fieldList: A whitelist of fields to be assigned to the entity. If not present
     *   the accessible fields list in the entity will be used.
     * - associated: A list of embedded documents you want to marshal.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array<string, mixed> $options List of options.
     */
    public function merge(EntityInterface $entity, array $data, array $options = []): EntityInterface
    {
        $options += ['associated' => []];
        [$data, $options] = $this->_prepareDataAndOptions($data, $options);

        $isNew = $entity->isNew();
        $errors = $this->_validate($data, $options, $isNew);
        $entity->setErrors($errors);

        // Handle invalid fields
        foreach (array_keys($errors) as $badKey) {
            if (isset($data[$badKey]) && $entity instanceof InvalidPropertyInterface) {
                $entity->setInvalidField($badKey, $data[$badKey]);
            }

            unset($data[$badKey]);
        }

        foreach ($this->index->embedded() as $embed) {
            $property = $embed->getProperty();
            if (in_array($embed->getAlias(), $options['associated'], true) && isset($data[$property])) {
                $data[$property] = $this->mergeNested($embed, $this->fieldValue($entity, $property), $data[$property]);
            }
        }

        if (!isset($options['fieldList'])) {
            $entity->patch($data);

            $this->dispatchAfterMarshal($entity, $data, $options);

            return $entity;
        }

        foreach ((array)$options['fieldList'] as $field) {
            assert(is_string($field));
            if (array_key_exists($field, $data)) {
                $entity->set($field, $data[$field]);
            }
        }

        $this->dispatchAfterMarshal($entity, $data, $options);

        return $entity;
    }

    /**
     * Merges each of the elements from `$data` into each of the entities in `$entities`
     *
     * Records in `$data` are matched against the entities using the id field.
     * Entries in `$entities` that cannot be matched to any record in
     * `$data` will be discarded. Records in `$data` that could not be matched will
     * be marshalled as a new entity.
     *
     * ### Options:
     *
     * - fieldList: An allowed list of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     *
     * @param iterable<\Cake\Datasource\EntityInterface> $entities the entities that will get the
     *   data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array<string, mixed> $options List of options.
     * @return array<\Cake\Datasource\EntityInterface>
     */
    public function mergeMany(iterable $entities, array $data, array $options = []): array
    {
        $indexed = (new Collection($data))
            ->groupBy(function (array $element) {
                return $element['id'] ?? '';
            })
            ->map(function ($element, $key) {
                return $key === '' ? $element : $element[0];
            })
            ->toArray();

        $new = $indexed[''] ?? [];
        unset($indexed['']);
        $output = [];

        foreach ($entities as $entity) {
            if (!($entity instanceof EntityInterface)) {
                continue;
            }

            $id = $entity->get('id');
            if (!isset($indexed[$id])) {
                continue;
            }

            $output[] = $this->merge($entity, $indexed[$id], $options);
            unset($indexed[$id]);
        }

        $new = array_merge($indexed, $new);
        foreach ($new as $value) {
            $output[] = $this->one($value, $options);
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
    protected function _validate(array $data, array $options, bool $isNew): array
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
                sprintf('validate must be a boolean, a string or an object. Got %s.', gettype($options['validate'])),
            );
        }

        $validator = $options['validate'];
        assert($validator instanceof Validator);

        return $validator->validate($data, $isNew);
    }

    /**
     * Returns data and options prepared to validate and marshall.
     *
     * @param array<string, mixed> $data The data to prepare.
     * @param array<string, mixed> $options The options passed to this marshaller.
     * @return array An array containing prepared data and options.
     */
    protected function _prepareDataAndOptions(array $data, array $options): array
    {
        $options += ['validate' => true];
        $data = new ArrayObject($data);
        $options = new ArrayObject($options);
        $this->index->dispatchEvent('Model.beforeMarshal', ['data' => $data, 'options' => $options]);

        return [(array)$data, (array)$options];
    }

    /**
     * Dispatch Model.afterMarshal event.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity that was marshaled.
     * @param array<string, mixed> $data The data used for marshaling.
     * @param array<string, mixed> $options List of options.
     */
    protected function dispatchAfterMarshal(EntityInterface $entity, array $data, array $options = []): void
    {
        $data = new ArrayObject($data);
        $options = new ArrayObject($options);
        $this->index->dispatchEvent(
            'Model.afterMarshal',
            ['entity' => $entity, 'data' => $data, 'options' => $options],
        );
    }

    /**
     * Get the value of a field from an entity.
     *
     * Checks whether the field exists in the entity before getting the value
     * to avoid exceptions if property validation is strict.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to extract the field from.
     * @param string $field The field to extract.
     */
    protected function fieldValue(EntityInterface $entity, string $field): mixed
    {
        return $entity->has($field) ? $entity->get($field) : null;
    }
}
