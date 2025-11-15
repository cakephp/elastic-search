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
 * @since         0.0.1
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\TestSuite\Fixture;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\TestSuite\Fixture\MappingGenerator;
use Cake\ElasticSearch\TestSuite\TestCase;
use RuntimeException;

/**
 * Test case for MappingGenerator
 */
class MappingGeneratorTest extends TestCase
{
    protected Connection $connection;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
    }

    /**
     * Teardown method - clean up any created indexes
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test indexes that might have been created
        $testIndexes = ['test_index_1', 'test_index_2', 'test_index_3'];
        foreach ($testIndexes as $indexName) {
            $index = $this->connection->getIndex($indexName);
            if ($index->exists()) {
                $index->delete();
            }
        }
    }

    /**
     * Test reload with all indexes
     */
    public function testReloadAllIndexes(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
            [
                'name' => 'test_index_2',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload();

        // Verify both indexes were created
        $index1 = $this->connection->getIndex('test_index_1');
        $index2 = $this->connection->getIndex('test_index_2');

        $this->assertTrue($index1->exists(), 'Index 1 should exist');
        $this->assertTrue($index2->exists(), 'Index 2 should exist');

        unlink($file);
    }

    /**
     * Test reload with specific indexes filter
     */
    public function testReloadSpecificIndexes(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
            [
                'name' => 'test_index_2',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
            [
                'name' => 'test_index_3',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'content' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');

        // Only reload test_index_1 and test_index_3
        $generator->reload(['test_index_1', 'test_index_3']);

        // Verify only specified indexes were created
        $index1 = $this->connection->getIndex('test_index_1');
        $index2 = $this->connection->getIndex('test_index_2');
        $index3 = $this->connection->getIndex('test_index_3');

        $this->assertTrue($index1->exists(), 'Index 1 should exist');
        $this->assertFalse($index2->exists(), 'Index 2 should not exist');
        $this->assertTrue($index3->exists(), 'Index 3 should exist');

        unlink($file);
    }

    /**
     * Test reload with single index filter
     */
    public function testReloadSingleIndex(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
            [
                'name' => 'test_index_2',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_2']);

        $index1 = $this->connection->getIndex('test_index_1');
        $index2 = $this->connection->getIndex('test_index_2');

        $this->assertFalse($index1->exists(), 'Index 1 should not exist');
        $this->assertTrue($index2->exists(), 'Index 2 should exist');

        unlink($file);
    }

    /**
     * Test reload with empty filter array reloads nothing
     */
    public function testReloadWithEmptyFilter(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload([]);

        $index1 = $this->connection->getIndex('test_index_1');
        $this->assertFalse($index1->exists(), 'No indexes should be created with empty filter');

        unlink($file);
    }

    /**
     * Test reload with non-existent index name in filter
     */
    public function testReloadWithNonExistentIndexFilter(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');

        // Filter includes index that doesn't exist in mappings
        $generator->reload(['nonexistent_index', 'test_index_1']);

        $index1 = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index1->exists(), 'test_index_1 should exist');

        unlink($file);
    }

    /**
     * Test reload drops existing index before recreating
     */
    public function testReloadDropsExistingIndex(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
        ]);

        // Create index first time
        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists());

        // Reload should drop and recreate
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists(), 'Index should exist after reload');

        unlink($file);
    }

    /**
     * Test exception when mapping file doesn't exist
     */
    public function testReloadWithInvalidFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The `/nonexistent/file.php` file does not exist');

        $generator = new MappingGenerator('/nonexistent/file.php', 'test');
        $generator->reload();
    }

    /**
     * Test exception when mapping has no name
     */
    public function testReloadWithMissingName(): void
    {
        $file = $this->createMappingFile([
            [
                'mapping' => [
                    'id' => ['type' => 'integer'],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The mapping at index 0 does not have a name');

        $generator = new MappingGenerator($file, 'test');
        $generator->reload();

        unlink($file);
    }

    /**
     * Test exception when mapping has no mapping key
     */
    public function testReloadWithMissingMapping(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapping for test_index_1 does not define a `mapping` key');

        $generator = new MappingGenerator($file, 'test');
        $generator->reload();

        unlink($file);
    }

    /**
     * Test with settings in mapping
     */
    public function testReloadWithSettings(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                ],
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists());

        unlink($file);
    }

    /**
     * Test simple mapping format (backward compatibility)
     */
    public function testSimpleMappingFormat(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                    'body' => ['type' => 'text'],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists());

        // Verify mapping was created correctly
        $mapping = $index->getMapping();
        $this->assertNotEmpty($mapping);
        $this->assertArrayHasKey('properties', $mapping);

        $properties = $mapping['properties'];
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('body', $properties);

        unlink($file);
    }

    /**
     * Test full mapping format with properties key
     */
    public function testFullMappingFormatWithProperties(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'text'],
                        'body' => ['type' => 'text'],
                    ],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists());

        // Verify mapping was created correctly
        $mapping = $index->getMapping();
        $this->assertNotEmpty($mapping);
        $this->assertArrayHasKey('properties', $mapping);

        $properties = $mapping['properties'];
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('body', $properties);

        unlink($file);
    }

    /**
     * Test full mapping format with dynamic_templates
     */
    public function testFullMappingFormatWithDynamicTemplates(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'text'],
                    ],
                    'dynamic_templates' => [
                        [
                            'strings_as_keywords' => [
                                'match_mapping_type' => 'string',
                                'mapping' => [
                                    'type' => 'keyword',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload(['test_index_1']);

        $index = $this->connection->getIndex('test_index_1');
        $this->assertTrue($index->exists());

        // Verify mapping was created with dynamic_templates
        $mapping = $index->getMapping();
        $this->assertNotEmpty($mapping);
        $this->assertArrayHasKey('properties', $mapping);
        $this->assertArrayHasKey('dynamic_templates', $mapping);

        $dynamicTemplates = $mapping['dynamic_templates'];
        $this->assertCount(1, $dynamicTemplates);
        $this->assertArrayHasKey('strings_as_keywords', $dynamicTemplates[0]);

        unlink($file);
    }

    /**
     * Test mixing simple and full mapping formats in same file
     */
    public function testMixedMappingFormats(): void
    {
        $file = $this->createMappingFile([
            [
                'name' => 'test_index_1',
                'mapping' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                ],
            ],
            [
                'name' => 'test_index_2',
                'mapping' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'text'],
                    ],
                    'dynamic' => 'strict',
                ],
            ],
        ]);

        $generator = new MappingGenerator($file, 'test');
        $generator->reload();

        $index1 = $this->connection->getIndex('test_index_1');
        $index2 = $this->connection->getIndex('test_index_2');

        $this->assertTrue($index1->exists());
        $this->assertTrue($index2->exists());

        // Verify both mappings were created correctly
        $mapping1 = $index1->getMapping();
        $mapping2 = $index2->getMapping();

        $this->assertNotEmpty($mapping1);
        $this->assertNotEmpty($mapping2);

        $this->assertArrayHasKey('properties', $mapping1);
        $this->assertArrayHasKey('properties', $mapping2);
        $this->assertArrayHasKey('dynamic', $mapping2);

        unlink($file);
    }

    /**
     * Helper method to create a temporary mapping file
     *
     * @param array $mappings The mappings to write to the file
     * @return string Path to the created file
     */
    protected function createMappingFile(array $mappings): string
    {
        $file = sys_get_temp_dir() . '/test_mappings_' . uniqid() . '.php';
        $content = '<?php return ' . var_export($mappings, true) . ';';
        file_put_contents($file, $content);

        return $file;
    }
}
