<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\Association;

use Cake\ElasticSearch\Document;

/**
 * Represents an embedded document that only contains
 * multiple instances.
 */
class EmbedMany extends Embedded
{
    /**
     * @inheritDoc
     */
    public function hydrate(array $data, array $options): Document|array
    {
        $class = $this->getEntityClass();
        $out = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $out[] = new $class($row, $options);
            }
        }

        return $out;
    }

    /**
     * @inheritDoc
     */
    public function type(): string
    {
        return static::ONE_TO_MANY;
    }
}
