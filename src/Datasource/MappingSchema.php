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
namespace Cake\ElasticSearch\Datasource;

/**
 * Object interface for elastic search mapping information.
 */
class MappingSchema
{
    /**
     * The raw mapping data from elasticsearch
     *
     * @var array
     */
    protected $data;

    /**
     * The name of the type this mapping data is for.
     *
     * @var string
     */
    protected $name;

    /**
     * Constructor
     *
     * @param string $name The name of the type of the mapping data
     * @param array $data The mapping data from elasticsearch
     */
    public function __construct($name, array $data)
    {
        $this->name = $name;
        if (isset($data[$name]['properties'])) {
            $data = $data[$name]['properties'];
        }
        $this->data = $data;
    }

    /**
     * Get the name of the type for this mapping.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get the mapping information for a single field.
     *
     * Can access nested fields through dot paths.
     *
     * @param string $name The path to the field you want.
     * @return array|null Either field mapping data or null.
     */
    public function field($name)
    {
        if (strpos($name, '.') === false) {
            if (isset($this->data[$name])) {
                return $this->data[$name];
            }

            return null;
        }
        $parts = explode('.', $name);
        $pointer = $this->data;
        foreach ($parts as $part) {
            if (isset($pointer[$part]['type']) && $pointer[$part]['type'] !== 'nested') {
                return $pointer[$part];
            }
            if (isset($pointer[$part]['properties'])) {
                $pointer = $pointer[$part]['properties'];
            }
        }
    }

    /**
     * Get the field type for a field.
     *
     * Can access nested fields through dot paths.
     *
     * @param string $name The path to the field you want.
     * @return string|null Either type information or null
     */
    public function fieldType($name)
    {
        $field = $this->field($name);
        if (!$field) {
            return null;
        }

        return $field['type'];
    }

    /**
     * Get the field names in the mapping.
     *
     * Will only return the top level fields. Nested object field names will
     * not be included.
     *
     * @return array
     */
    public function fields()
    {
        return array_keys($this->data);
    }
}
