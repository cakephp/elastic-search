<?php
declare(strict_types=1);

namespace Cake\ElasticSearch\TestSuite\Fixture;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Mapping as ElasticaMapping;
use RuntimeException;

/**
 * Create indexes and mappings for test cases.
 *
 * Index definition files should return an array of indexes
 * to create. Each index in the array should follow the form of
 *
 * ```
 * [
 *   'name' => 'articles',
 *   'mapping' => [...],
 *   'settings' => [...],
 * ]
 * ```
 *
 * The `mapping` key should be compatible with Elasticsearch's
 * mapping API and Elastica.
 *
 * The `settings` key can contain Elastica compatible index creation
 * settings.
 *
 * @see https://elastica.io/getting-started/storing-and-indexing-documents.html#define-mapping
 */
class MappingGenerator
{
    /**
     * @var string
     */
    protected string $file;

    /**
     * @var string
     */
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
     * @param array<string> $indexes A subset of indexes to reload. Used for testing.
     * @return void
     */
    public function reload(?array $indexes = null): void
    {
        $db = ConnectionManager::get($this->connection);
        if (!($db instanceof Connection)) {
            throw new RuntimeException("The `{$this->connection}` connection is not an ElasticSearch connection.");
        }
        $mappings = include $this->file;
        if (empty($mappings)) {
            throw new RuntimeException("The `{$this->file}` file did not return any mapping data.");
        }
        foreach ($mappings as $i => $mapping) {
            if (!isset($mapping['name'])) {
                throw new RuntimeException("The mapping at index {$i} does not have a name.");
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
     * @return void
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
     * @param \Cake\ElasticSearch\Datasource\Connection $db The connection.
     * @param array $mapping The index mapping and settings.
     * @return void
     */
    protected function createIndex(Connection $db, array $mapping): void
    {
        if (!isset($mapping['mapping'])) {
            throw new RuntimeException("Mapping for {$mapping['name']} does not define a `mapping` key");
        }

        $esIndex = $db->getIndex($mapping['name']);

        $args = [];
        if (!empty($mapping['settings'])) {
            $args['settings'] = $mapping['settings'];
        }
        $esIndex->create($args);

        $esMapping = new ElasticaMapping();
        $esMapping->setProperties($mapping['mapping']);

        $response = $esMapping->send($esIndex);
        if (!$response->isOk()) {
            $msg = sprintf(
                'Fixture creation for "%s" failed "%s"',
                $this->table,
                $response->getError()
            );
            throw new RuntimeException($msg);
        }
    }
}
