<?php
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
namespace Cake\ElasticSearch\Test\Datasource;

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;

/**
 * Tests the connection class
 *
 */
class ConnectionTest extends TestCase
{

    /**
     * Tests the getIndex method, in particular, that calling it with no arguments
     * will use the default index for the connection
     *
     * @return void
     */
    public function testGetIndex()
    {
        $connection = new Connection();
        $index = $connection->getIndex();
        $this->assertEquals('_all', $index->getName());

        $connection->setConfigValue('index', 'something_else,another');
        $index = $connection->getIndex();
        $this->assertEquals('something_else,another', $index->getName());

        $connection = new Connection(['index' => 'foobar']);
        $index = $connection->getIndex();
        $this->assertEquals('foobar', $index->getName());

        $index = $connection->getIndex('baz');
        $this->assertEquals('baz', $index->getName());
    }

    /**
     * Ensure the log option works via the constructor
     *
     * @return void
     */
    public function testConstructLogOption()
    {
        $connection = new Connection();
        $this->assertFalse($connection->logQueries());

        $opts = ['log' => true];
        $connection = new Connection($opts);
        $this->assertTrue($connection->logQueries());
    }

    /**
     * Ensure that logging queries works.
     *
     * @return void
     */
    public function testQueryLogging()
    {
        $logger = $this->getMockBuilder('Cake\Log\Engine\BaseLog')->setMethods(['log'])->getMock();
        $logger->expects($this->once())->method('log');
        Log::config('elasticsearch', $logger);

        $connection = ConnectionManager::get('test');
        $connection->logQueries(true);
        $result = $connection->request('_stats');
        $connection->logQueries(false);

        $this->assertNotEmpty($result);
    }
}
