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
     * Hydrate an instance from the parent documents data.
     *
     * @param array $data The data to use in the embedded document.
     * @param array $options The options to use in the new document.
     * @return array<\Cake\ElasticSearch\Document>
     * @psalm-suppress MoreSpecificReturnType
     */
    public function hydrate(array $data, array $options): array
    {
        $class = $this->getEntityClass();
        $out = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $document = new $class($row, $options);
                assert($document instanceof Document);
                $out[] = $document;
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
