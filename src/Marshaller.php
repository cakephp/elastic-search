<?php
namespace Cake\ElasticSearch;

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
     * * associated: Associations listed here will be marshalled as well.
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
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
}
