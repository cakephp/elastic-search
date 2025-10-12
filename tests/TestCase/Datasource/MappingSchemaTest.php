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
 * @since         0.5.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\Datasource;

use Cake\ElasticSearch\Datasource\MappingSchema;
use Cake\ElasticSearch\TestSuite\TestCase;

/**
 * Test case for the MappingSchema
 */
class MappingSchemaTest extends TestCase
{
    /**
     * Test the name()
     */
    public function testName(): void
    {
        $mapping = new MappingSchema('articles', []);
        $this->assertSame('articles', $mapping->name());
    }

    /**
     * Test fields()
     */
    public function testFields(): void
    {
        $data = [
            'user_id' => [
                'type' => 'integer',
            ],
            'title' => [
                'type' => 'text',
            ],
            'body' => [
                'type' => 'text',
            ],
        ];
        $mapping = new MappingSchema('articles', $data);
        $expected = array_keys($data);
        $this->assertEquals($expected, $mapping->fields());
    }

    /**
     * Test field()
     */
    public function testField(): void
    {
        $data = [
            'user_id' => [
                'type' => 'integer',
            ],
            'title' => [
                'type' => 'text',
                'null_value' => 'na',
            ],
            'body' => [
                'type' => 'text',
            ],
        ];
        $mapping = new MappingSchema('articles', $data);
        $this->assertEquals($data['user_id'], $mapping->field('user_id'));
        $this->assertEquals($data['title'], $mapping->field('title'));
        $this->assertNull($mapping->field('nope'));
    }

    /**
     * Test field()
     */
    public function testFieldNested(): void
    {
        $data = [
            'user_id' => [
                'type' => 'integer',
            ],
            'address' => [
                'type' => 'nested',
                'properties' => [
                    'street' => ['type' => 'text'],
                ],
            ],
        ];
        $mapping = new MappingSchema('articles', $data);
        $this->assertEquals(['type' => 'text'], $mapping->field('address.street'));
        $this->assertNull($mapping->field('address.nope'));
    }

    /**
     * Test fieldType()
     */
    public function testFieldType(): void
    {
        $data = [
            'user_id' => [
                'type' => 'integer',
            ],
            'address' => [
                'type' => 'nested',
                'properties' => [
                    'street' => ['type' => 'text'],
                ],
            ],
        ];
        $mapping = new MappingSchema('articles', $data);
        $this->assertSame('integer', $mapping->fieldType('user_id'));
        $this->assertSame('text', $mapping->fieldType('address.street'));
        $this->assertNull($mapping->fieldType('address.nope'));
    }
}
