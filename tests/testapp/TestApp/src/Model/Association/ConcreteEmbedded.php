<?php
declare(strict_types=1);

namespace TestApp\Model\Association;

use Cake\ElasticSearch\Association\Embedded;
use Cake\ElasticSearch\Document;

/**
 * Concrete implementation of Embedded for testing
 */
class ConcreteEmbedded extends Embedded
{
    public function hydrate(array $data, array $options): Document|array
    {
        return new Document($data);
    }

    public function type(): string
    {
        return self::ONE_TO_ONE;
    }
}
