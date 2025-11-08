<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\TestSuite\Fixture;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use RuntimeException;

/**
 * Create indexes and mappings for test cases.
 *
 * Index definition files should return an array of indexes
 * to create. Each index in the array should follow the form of
 *
 * Simple mapping (properties only):
 * ```
 * [
 *   'name' => 'articles',
 *   'mapping' => [
 *     'title' => ['type' => 'text'],
 *     'body' => ['type' => 'text'],
 *   ],
 *   'settings' => [...],
 * ]
 * ```
 *
 * Full mapping structure (with dynamic_templates, etc.):
 * ```
 * [
 *   'name' => 'articles',
 *   'mapping' => [
 *     'properties' => [
 *       'title' => ['type' => 'text'],
 *       'body' => ['type' => 'text'],
 *     ],
 *     'dynamic_templates' => [...],
 *     'dynamic' => 'strict',
 *   ],
 *   'settings' => [...],
 * ]
 * ```
 *
 * The `mapping` key should be compatible with Elasticsearch's
 * mapping API and Elastica. If the mapping contains a 'properties'
 * key, it will be treated as a full mapping structure. Otherwise,
 * it will be wrapped in a 'properties' key for backward compatibility.
 *
 * The `settings` key can contain Elastica compatible index creation
 * settings.
 *
 * @see https://elastica.io/getting-started/storing-and-indexing-documents.html#define-mapping
 */
class MappingGenerator
{
    protected string $file;

    protected string $connection;

    /**
     * Constructor
     *
     * @param string $file The index definition file.
     * @param string $connection The connection to put indexes into.
     */
    public function __construct(string $file, string $connection)
    {
        $this->file = $file;
        $this->connection = $connection;
    }

    /**
     * Drop and re-create indexes defined in the mapping schema file.
     *
     * @param array<string>|null $indexes A subset of index names to reload. If null, all indexes are reloaded.
     */
    public function reload(?array $indexes = null): void
    {
        $db = ConnectionManager::get($this->connection);
        if (!($db instanceof Connection)) {
            throw new RuntimeException(sprintf(
                'The `%s` connection is not an ElasticSearch connection.',
                $this->connection,
            ));
        }

        if (!file_exists($this->file)) {
            throw new RuntimeException(sprintf('The `%s` file does not exist.', $this->file));
        }

        $mappings = include $this->file;
        if (empty($mappings)) {
            throw new RuntimeException(sprintf('The `%s` file did not return any mapping data.', $this->file));
        }

        foreach ($mappings as $i => $mapping) {
            if (!isset($mapping['name'])) {
                throw new RuntimeException(sprintf('The mapping at index %s does not have a name.', $i));
            }

            // Skip if indexes filter is provided and this index is not in the list
            if ($indexes !== null && !in_array($mapping['name'], $indexes, true)) {
                continue;
            }

            $this->dropIndex($db, $mapping['name']);
            $this->createIndex($db, $mapping);
        }
    }

    /**
     * Drop an index if it exists.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db The connection.
     * @param string $name The name of the index to drop.
     */
    protected function dropIndex(Connection $db, string $name): void
    {
        $esIndex = $db->getIndex($name);
        if ($esIndex->exists()) {
            $esIndex->delete();
        }
    }

    /**
     * Create an index.
     *
     * Supports two mapping formats:
     * - Simple: Direct property definitions (wrapped in 'properties' automatically)
     * - Full: Complete mapping structure including 'properties', 'dynamic_templates', etc.
     *
     * The format is detected by checking if the mapping contains a 'properties' key.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db The connection.
     * @param array $mapping The index mapping and settings.
     */
    protected function createIndex(Connection $db, array $mapping): void
    {
        if (!isset($mapping['mapping'])) {
            throw new RuntimeException(sprintf('Mapping for %s does not define a `mapping` key', $mapping['name']));
        }

        $esIndex = $db->getIndex($mapping['name']);

        $args = [];
        if (!empty($mapping['settings'])) {
            $args['settings'] = $mapping['settings'];
        }

        // Detect if this is a full mapping structure or simple properties
        if (!empty($mapping['mapping'])) {
            if (isset($mapping['mapping']['properties'])) {
                // Full mapping structure - use as-is to support dynamic_templates, etc.
                $args['mappings'] = $mapping['mapping'];
            } else {
                // Simple properties only - wrap for backward compatibility
                $args['mappings'] = [
                    'properties' => $mapping['mapping'],
                ];
            }
        }

        $response = $esIndex->create($args);
        if (!$response->isOk()) {
            $msg = sprintf(
                'Fixture creation for "%s" failed "%s"',
                $mapping['name'],
                $response->getError(),
            );
            throw new RuntimeException($msg);
        }
    }
}
