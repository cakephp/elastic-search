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
require dirname(__DIR__) . '/vendor/autoload.php';

define('APP', __DIR__);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

Configure::write('App', [
    'namespace' => 'App',
    'paths' => [
        'plugins' => [APP . DS . 'testapp' . DS . 'Plugin' . DS],
    ]
]);

Cache::config('_cake_core_', [
    'className' => 'File',
    'path' => sys_get_temp_dir(),
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?index=cake_test_db&driver=Cake\ElasticSearch\Datasource\Connection');
}

ConnectionManager::config('test', ['url' => getenv('db_dsn')]);
ConnectionManager::config('test_elastic', ['url' => getenv('db_dsn')]);
