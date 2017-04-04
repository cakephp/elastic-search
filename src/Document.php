<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
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
     * Holds an instance of a Result object that's passed into the constructor
     * from a search query. It can contain extra information about this document
     * concerning the search operation, such as highlights, score and version.
     *
     * @var \Elastica\Result
     */
    protected $_result;

    /**
     * Takes either an array or a Result object form a search and constructs
     * a document representing an entity in a elastic search type,
     *
     * @param array|\Elastica\Result $data An array or Result object that
     *  represents an Elasticsearch document
     * @param array $options An array of options to set the state of the
     *  document
     */
    public function __construct($data = [], $options = [])
    {
        if ($data instanceof Result) {
            $options['result'] = $data;
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
            'source' => null,
            'result' => null
        ];
        if (!empty($options['source'])) {
            $this->source($options['source']);
        }

        if ($options['markNew'] !== null) {
            $this->isNew($options['markNew']);
        }

        if ($options['result'] !== null) {
            $this->_result = $options['result'];
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

    /**
     * Returns the ElasticSearch type name from which this document came from.
     *
     * If this is a new document, this function returns null
     *
     * @return string|null
     */
    public function type()
    {
        if ($this->_result) {
            return $this->_result->getType();
        }

        return null;
    }

    /**
     * Returns the version number of this document as returned by ElasticSearch
     *
     * If this is a new document, this function returns 1
     *
     * @return int
     */
    public function version()
    {
        if ($this->_result) {
            return $this->_result->getVersion();
        }

        return 1;
    }

    /**
     * Returns the highlights array for document as returned by ElasticSearch
     * for the executed query.
     *
     * If this is a new document, or the query used to create it did not ask for
     * highlights, this function will return an empty array.
     *
     * @return array
     */
    public function highlights()
    {
        if ($this->_result) {
            return $this->_result->getHighlights();
        }

        return [];
    }

    /**
     * Returns the explanation array for this document as returned from ElasticSearch.
     *
     * If this is a new document, or the query used to create it did not ask for
     * explanation, this function will return an empty array.
     *
     * @return array
     */
    public function explanation()
    {
        if ($this->_result) {
            return $this->_result->getExplanation();
        }

        return [];
    }
}
