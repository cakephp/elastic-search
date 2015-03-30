<?php

namespace Cake\ElasticSearch;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;
use Elastica\Result;

/**
 * Represents a document stored in a Elastic Search Type
 *
 */
class Document implements EntityInterface
{

    use EntityTrait;

    /**
     * Holds an instance to a Result object is passed in the constructor from
     * a search query. It can contain extra information about this document
     * concerning the search operation, such as highlights, score and version.
     *
     * @var \Elastica\Result
     */
    protected $_result;

    /**
     * Takes either an array or a Result object form a serach and constructs
     * a document representing an enty in a elastic search type,
     *
     * @param array|Elastica\Result $data
     * @param array $options
     */
    public function __construct($data = [], $options = [])
    {
        if ($data instanceof Result) {
            $this->_result = $data;
            $data = $data->getData();
        }

        $options += [
            'useSetters' => true,
            'markClean' => false,
            'markNew' => null,
            'guard' => false,
            'source' => null
        ];
        if (!empty($options['source'])) {
            $this->source($options['source']);
        }

        if ($options['markNew'] !== null) {
            $this->isNew($options['markNew']);
        }

        if (!empty($data) && $options['markClean'] && !$options['useSetters']) {
            $this->_properties = $data;
            return;
        }

        if (!empty($data)) {
            $this->set($data, [
                'setter' => $options['useSetters'],
                'guard' => $options['guard']
            ]);
        }

        if ($options['markClean']) {
            $this->clean();
        }
    }
}
