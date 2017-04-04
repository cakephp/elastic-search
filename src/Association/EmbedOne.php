<?php
namespace Cake\ElasticSearch\Association;

/**
 * Represents an embedded document that only contains
 * one instance.
 */
class EmbedOne extends Embedded
{
    /**
     * Hydrate an instance from the parent documents data.
     *
     * @param array $data The data to use in the embedded document.
     * @param array $options The options to use in the new document.
     * @return \Cake\ElasticSearch\Document
     */
    public function hydrate(array $data, $options)
    {
        $class = $this->entityClass();

        return new $class($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function type()
    {
        return static::ONE_TO_ONE;
    }
}
