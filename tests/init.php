<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

Configure::write('App', [
    'namespace' => 'App'
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=Cake\ElasticSearch\Datasource\Connection://localhost:9200?driver=Cake\ElasticSearch\Datasource\Connection');
}

ConnectionManager::config('test', ['url' => getenv('db_dsn')]);
