<?php

namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\Type;
use Cake\TestSuite\TestCase;

/**
 * Tests the connection class
 *
 */
class TypeTest extends TestCase {

/**
 * Tests that calling find will return a query object
 *
 * @return void
 */
	public function testFindAll() {
		$type = new Type();
		$query = $type->find('all');
		$this->assertInstanceOf('Cake\ElasticSearch\Query', $query);
		$this->assertSame($type, $query->repository());
	}

}
