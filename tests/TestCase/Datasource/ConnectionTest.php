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
namespace Cake\ElasticSearch\Test\Datasource;

use Cake\Core\Exception\Exception;
use Cake\Database\Exception\MissingDriverException;
use Cake\Database\Log\LoggedQuery;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Database\Driver\Elasticsearch;
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
        $this->expectException(MissingDriverException::class);

        $connection = new Connection();
        $index = $connection->getIndex();
    }

    /**
     * Tests a connection build by ConnectionManager and assert
     * it Driver type.
     *
     * @return Connection
     */
    public function testValidConnection()
    {
        $connection = ConnectionManager::get('test');
        $this->assertInstanceOf(Elasticsearch::class, $connection->getDriver());

        return $connection;
    }

    /**
     * Tests the getIndex method when defining a index name from different
     * ways
     *
     * @depends testValidConnection
     * @return void
     */
    public function testGetIndex($connection)
    {
        $index = $connection->getIndex('something_else,another');
        $this->assertEquals('something_else,another', $index->getName());

        $index = $connection->getIndex('baz');
        $this->assertEquals('baz', $index->getName());
    }

    /**
     * Ensure the log option works via the constructor
     *
     * @depends testValidConnection
     * @return void
     */
    public function testConstructLogOption($connection)
    {
        $this->assertFalse($connection->logQueries());

        $config = ConnectionManager::parseDsn(getenv('db_dsn'));
        $config['log'] = true;
        $connection = new Connection($config);

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

        $config = ConnectionManager::parseDsn(getenv('db_dsn'));
        $connection = new Connection($config);
        $connection->enableQueryLogging(true);
        $result = $connection->request('_stats');

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

        $config = ConnectionManager::parseDsn(getenv('db_dsn'));
        $connection = new Connection($config);
        $connection->setLogger($logger);
        $connection->logQueries(true);
        $result = $connection->request('_stats');

        $this->assertNotEmpty($result);
    }

    /**
     * Ensure the esLogger option validate via the constructor
     *
     * @depends testValidConnection
     * @return void
     */
    public function testConstructInvalidEsLogggerOption($connection)
    {
        $config = ConnectionManager::parseDsn(getenv('db_dsn'));
        $config['esLogger'] = 'invalid';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Value of 'esLogger' must implement \Psr\Log\LoggerInterface");
        $connection = new Connection($config);
    }

    /**
     * Ensure the esLogger option works via the constructor
     *
     * @depends testValidConnection
     * @return void
     */
    public function testConstructEsLoggerOption($connection)
    {
        $esLogger = $this->getMockBuilder('Cake\ElasticSearch\Datasource\Log\ElasticLogger')
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();

        $esLogger->expects($this->once())->method('log')->with(
            $this->equalTo('debug'),
            $this->equalTo('Elastica Request')
        );

        $config = ConnectionManager::parseDsn(getenv('db_dsn'));
        $config['log'] = true;
        $config['esLogger'] = $esLogger;

        $connection = new Connection($config);
        $result = $connection->request('_stats');

        $this->assertNotEmpty($result);
    }
}
