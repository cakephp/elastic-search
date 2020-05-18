<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link      http://cakephp.org CakePHP(tm) Project
 * @since     0.0.1
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
require dirname(__DIR__) . '/vendor/autoload.php';

define('CAKE', dirname(__DIR__) . '/vendor/cakephp/cakephp/src/');

require CAKE . 'basics.php';

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
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
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

Log::setConfig('debug', [
    'engine' => FileLog::class,
    'levels' => ['notice', 'info', 'debug'],
    'path' => LOGS,
    'file' => 'debug',
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?driver=Cake\ElasticSearch\Datasource\Connection');
}

ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);
ConnectionManager::setConfig('test_elastic', ['url' => getenv('db_dsn')]);

Router::reload();
