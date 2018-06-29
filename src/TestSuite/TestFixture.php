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
namespace Cake\ElasticSearch\TestSuite;

use Cake\Core\Exception\Exception as CakeException;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\FixtureInterface;
use Cake\ElasticSearch\IndexRegistry;
use Cake\Utility\Inflector;
use Elastica\Query\MatchAll;
use Elastica\Type\Mapping as ElasticaMapping;

/**
 * A Test fixture implementation for elastic search.
 *
 * Lets you seed indexes for testing your application.
 *
 * Class extension is temporary as fixtures are missing an interface.
 */
class TestFixture implements FixtureInterface
{

    /**
     * Full Table Name
     *
     * @var string
     */
    public $table = null;

    /**
     * The connection name to use for this fixture.
     *
     * @var string
     */
    public $connection = 'test';

    /**
     * The Elastic search type mapping definition for this type.
     *
     * The schema defined here should be compatible with Elasticsearch's
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
     * Instantiate the fixture.
     *
     * @throws \Cake\Core\Exception\Exception on invalid datasource usage.
     */
    public function __construct()
    {
        if (!empty($this->connection)) {
            $connection = $this->connection;
            if (strpos($connection, 'test') !== 0) {
                $message = sprintf(
                    'Invalid datasource name "%s" for "%s" fixture. Fixture datasource names must begin with "test".',
                    $connection,
                    $this->table
                );
                throw new CakeException($message);
            }
        }

        $this->init();
    }

    /**
     * Initialize the fixture.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Return the index class from table name
     *
     * @return \Cake\ElasticSearch\Index
     */
    public function getIndex()
    {
        $name = Inflector::camelize($this->table);

        return IndexRegistry::get($name);
    }

    /**
     * Create index and mapping for the type.
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     * @return void
     */
    public function create(ConnectionInterface $db)
    {
        if (empty($this->schema)) {
            return;
        }

        $esIndex = $db->getIndex($this->getIndex()->getName());
        if ($esIndex->exists()) {
            $esIndex->delete();
        }
        $esIndex->create();

        $type = $esIndex->getType($this->getIndex()->getType());
        $mapping = new ElasticaMapping();
        $mapping->setType($type);
        $mapping->setProperties($this->schema);

        $response = $mapping->send();
        if (!$response->isOk()) {
            $msg = sprintf(
                'Fixture creation for "%s" failed "%s"',
                $this->table,
                $response->getError()
            );
            Log::error($msg);
            trigger_error($msg, E_USER_WARNING);

            return false;
        }

        $this->created[] = $db->configName();

        return true;
    }

    /**
     * Insert fixture documents.
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     * @return void
     */
    public function insert(ConnectionInterface $db)
    {
        if (empty($this->records)) {
            return;
        }
        $documents = [];
        $esIndex = $db->getIndex($this->getIndex()->getName());
        $type = $esIndex->getType($this->getIndex()->getType());

        foreach ($this->records as $data) {
            $id = '';
            if (isset($data['id'])) {
                $id = $data['id'];
            }
            unset($data['id']);
            $documents[] = $type->createDocument($id, $data);
        }
        $type->addDocuments($documents);
        $esIndex->refresh();
    }

    /**
     * Drops the index
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     * @return void
     */
    public function drop(ConnectionInterface $db)
    {
        $esIndex = $db->getIndex($this->getIndex()->getName());

        if ($esIndex->exists()) {
            $esIndex->delete();
        }
    }

    /**
     * Truncate the fixture type.
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     * @return void
     */
    public function truncate(ConnectionInterface $db)
    {
        $query = new MatchAll();
        $esIndex = $db->getIndex($this->getIndex()->getName());
        $type = $esIndex->getType($this->getIndex()->getType());
        $type->deleteByQuery($query);
        $esIndex->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function sourceName()
    {
        return $this->table;
    }

    /**
     * No-op method needed because of the Fixture interface.
     * Elasticsearch does not deal with foreign key constraints.
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     * @return void
     */
    public function createConstraints(ConnectionInterface $db)
    {
    }

    /**
     * No-op method needed because of the Fixture interface.
     * Elasticsearch does not deal with foreign key constraints.
     *
     * @param \Cake\Datasource\ConnectionInterface $db The Elasticsearch connection
     *  connection
     * @return void
     */
    public function dropConstraints(ConnectionInterface $db)
    {
    }
}
