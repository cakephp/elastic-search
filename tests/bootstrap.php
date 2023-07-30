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
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.0.1
 * @license   https://www.opensource.org/licenses/mit-license.php MIT License
 */
require dirname(__DIR__) . '/vendor/autoload.php';

define('CAKE', dirname(__DIR__) . '/vendor/cakephp/cakephp/src/');

define('ROOT', dirname(__DIR__));
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('APP', __DIR__);
define('TMP', sys_get_temp_dir() . DS);
define('LOGS', TMP . 'logs' . DS);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ElasticSearch\IndexRegistry;
use Cake\ElasticSearch\TestSuite\Fixture\MappingGenerator;
use Cake\Routing\Router;

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'paths' => [
        'plugins' => [APP . DS . 'testapp' . DS . 'Plugin' . DS],
    ],
]);

Cache::setConfig('_cake_core_', [
    'className' => 'File',
    'path' => sys_get_temp_dir(),
]);

if (!getenv('DB_URL')) {
    putenv('DB_URL=Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?driver=Cake\ElasticSearch\Datasource\Connection');
}

ConnectionManager::setConfig('elastic', ['url' => getenv('DB_URL')]);
ConnectionManager::setConfig('test', ['url' => getenv('DB_URL')]);

$schema = new MappingGenerator('./tests/mappings.php', 'test');
$schema->reload();

Router::reload();

$indexRegistry = new IndexRegistry();
FactoryLocator::add('Elastic', $indexRegistry);
FactoryLocator::add('ElasticSearch', $indexRegistry);
