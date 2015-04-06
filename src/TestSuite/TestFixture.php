<?php
namespace Cake\ElasticSearch\TestSuite;

use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Document as ElasticaDocument;

/**
 * A Test fixture implementation for elastic search.
 *
 * Lets you seed indexes for testing your application.
 *
 * Class extension is temporary as fixtures are missing an interface.
 */
class TestFixture
{
    /**
     * The connection name to use for this fixture.
     *
     * @var string
     */
    public $connection = 'test';

    /**
     * The Elastic search type definition for this type.
     *
     * The schema defined here should be compatible with ElasticSearch's
     * mapping API and Elastica
     *
     * @var array
     * @see http://elastica.io/getting-started/storing-and-indexing-documents.html#define-mapping
     */
    public $schema = [];

    /**
     * The records to insert.
     *
     * @var array
     */
    public $records = [];

    /**
     * A list of connections this fixtures has been added to.
     *
     * @var array
     */
    public $created = [];

    /**
     * Create the mapping for the type.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db
     * @return void
     */
    public function create(Connection $db)
    {
        if (empty($this->schema)) {
            return;
        }
        $db->setMapping($this->table, $this->schema);
    }

    /**
     * Insert fixture documents.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db
     * @return void
     */
    public function insert(Connection $db)
    {
        if (empty($this->records)) {
            return;
        }
        $documents = [];
        $index = $db->getIndex();
        $type = $index->getType($this->table);

        foreach ($this->records as $data) {
            $id = '';
            if (isset($data['id'])) {
                $id = $data['id'];
            }
            unset($data['id']);
            $documents[] = $type->createDocument($id, $data);
        }
        $type->addDocuments($documents);
        $index->refresh();
    }

    /**
     * Drops a mapping and all its related data.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db
     * @return void
     */
    public function drop(Connection $db)
    {
        $index = $db->getIndex();
        $type = $index->getType($this->table);
        $type->delete();
    }

    /**
     * Truncate the fixture type.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $db
     * @return void
     */
    public function truncate(Connection $db)
    {
        $ids = [];
        foreach ($this->records as $record) {
            if (isset($record['id'])) {
                $ids[] = $record['id'];
            }
        }
        if (empty($ids)) {
            return;
        }
        $index = $db->getIndex();
        $type = $index->getType($this->table);
        $type->deleteByIds($ids);
    }
}
