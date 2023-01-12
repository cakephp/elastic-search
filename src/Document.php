<?php
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
     * Takes either an array or a Result object form a search and constructs
     * a document representing an entity in a elastic search type,
     *
     * @param array|Elastica\Result $data An array or Result object that
     *  represents an Elasticsearch document
     * @param array $options An array of options to set the state of the
     *  document
     */
    public function __construct($data = [], $options = [])
    {
        if ($data instanceof Result) {
            $this->_result = $data;
            $id = $data->getId();
            $data = $data->getData();
            if ($id !== []) {
                $data['id'] = $id;
            }
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
