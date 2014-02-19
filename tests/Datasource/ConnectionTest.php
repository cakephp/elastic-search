<?php

namespace Cake\ElasticSearch\Test\Datasource;

use Cake\ElasticSearch\Datasource\Connection;
use Cake\TestSuite\TestCase;

/**
 * Tests the connection class
 *
 */
class ConnectionTest extends TestCase {

/**
 * Tests the getIndex method, in particular, that calling it with no arguments
 * will use the default index for the connection
 *
 * @return void
 */
	public function testGetIndex() {
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
}
