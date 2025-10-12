<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\Association;

use Cake\ElasticSearch\Document;

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
     */
    public function hydrate(array $data, array $options): Document
    {
        $class = $this->getEntityClass();

        $document = new $class($data, $options);
        assert($document instanceof Document);

        return $document;
    }

    /**
     * @inheritDoc
     */
    public function type(): string
    {
        return static::ONE_TO_ONE;
    }
}
