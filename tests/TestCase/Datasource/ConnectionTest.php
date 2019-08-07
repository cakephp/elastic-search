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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\Datasource;

use Cake\Database\Log\LoggedQuery;
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
     * Tests the getIndex method that calling it with no arguments,
     * which is not supported
     *
     * @return void
     */
    public function testGetEmptyIndex()
    {
        $this->expectException(\ArgumentCountError::class);

        $connection = new Connection();
        $index = $connection->getIndex();
    }

    /**
     * Tests the getIndex method when defining a index name from different
     * ways
     *
     * @return void
     */
    public function testGetIndex()
    {
        $connection = new Connection();
        $index = $connection->getIndex('something_else,another');
        $this->assertEquals('something_else,another', $index->getName());

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
        $this->assertInstanceOf('\Cake\Log\Engine\FileLog', $connection->getLogger());
    }

    /**
     * Ensure that logging queries works.
     *
     * @return void
     */
    public function testLoggerFileLog()
    {
        $logger = $this->getMockBuilder('Cake\Log\Engine\FileLog')->setMethods(['log'])->getMock();

        $message = json_encode([
            'method' => 'GET',
            'path' => '_stats',
            'data' => [],
        ], JSON_PRETTY_PRINT);

        $logger->expects($this->once())->method('log')->with(
            $this->equalTo('debug'),
            $this->equalTo($message)
        );

        Log::setConfig('elasticsearch', $logger);

        $connection = ConnectionManager::get('test');
        $connection->logQueries(true);
        $result = $connection->request('_stats');
        $connection->logQueries(false);

        $this->assertNotEmpty($result);
    }

    /**
     * Ensure that logging queries works.
     *
     * @return void
     */
    public function testLoggerQueryLogger()
    {
        $logger = $this->getMockBuilder('Cake\Database\Log\QueryLogger')->setMethods(['log'])->getMock();
        $logger->expects($this->once())->method('log');

        $query = new LoggedQuery();
        $query->query = json_encode([
            'method' => 'GET',
            'path' => '_stats',
            'data' => [],
        ], JSON_PRETTY_PRINT);

        $logger->expects($this->once())->method('log')->with($query);

        $connection = ConnectionManager::get('test');
        $connection->setLogger($logger);
        $connection->logQueries(true);
        $result = $connection->request('_stats');
        $connection->logQueries(false);

        $this->assertNotEmpty($result);
    }
}
