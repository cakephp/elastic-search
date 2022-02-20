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
     * @inheritDoc
     */
    public function hydrate(array $data, array $options): Document|array
    {
        $class = $this->getEntityClass();

        return new $class($data, $options);
    }

    /**
     * @inheritDoc
     */
    public function type(): string
    {
        return static::ONE_TO_ONE;
    }
}
