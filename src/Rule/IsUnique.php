<?php
declare(strict_types=1);

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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Rule;

use Cake\Datasource\EntityInterface;

/**
 * Checks that a list of fields from an entity are unique in the table
 *
 * Note: This rule uses 'is' conditions which translate to ElasticSearch 'term' queries.
 * For ElasticSearch 'text' fields that are analyzed (tokenized, lowercased, etc.),
 * term queries may not work as expected for exact matching. Consider using 'keyword'
 * field mappings or '.keyword' subfields for exact unique validation.
 */
class IsUnique
{
    /**
     * The list of fields to check
     */
    protected array $_fields;

    /**
     * Constructor.
     *
     * ### Options
     *
     * - `filterNullFields` Set to false to allow keys with null values in the
     *   the conditions array.
     *
     * @param array $fields The list of fields to check uniqueness for
     */
    public function __construct(array $fields)
    {
        $this->_fields = $fields;
    }

    /**
     * Performs the uniqueness check
     *
     * Note: Uses 'field is value' conditions which create ElasticSearch term queries.
     * These work best with 'keyword' type fields. For 'text' fields that are analyzed,
     * the validation may not work as expected due to ElasticSearch text analysis.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity from where to extract the fields
     *   where the `repository` key is required.
     *
     * @param array $options Options passed to the check,
     */
    public function __invoke(EntityInterface $entity, array $options): bool
    {
        if (!$entity->extract($this->_fields, true)) {
            return true;
        }

        $fields = $entity->extract($this->_fields);
        $conditions = [];

        foreach ($fields as $field => $value) {
            $conditions[$field . ' is'] = $value;
        }

        if ($entity->isNew() === false) {
            $conditions['_id is not'] = $entity->get('id');
        }

        return !$options['repository']->exists($conditions);
    }
}
