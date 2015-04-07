<?php
namespace Cake\ElasticSearch\Datasource;

/**
 * Temporary shim for fixtures as they know too much about databases.
 */
class SchemaCollection
{
    public function listTables()
    {
        return [];
    }
}
