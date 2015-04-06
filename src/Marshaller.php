<?php
namespace Cake\ElasticSearch;

use Cake\ElasticSearch\Type;

/**
 * Contains logic to convert array data into document objects.
 *
 * Useful when converting request data into documents.
 */
class Marshaller extends OrmMarshaller
{
    protected $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    /**
     * Hydrate a single document.
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

    public function many(array $data, array $options = [])
    {
    }
}
