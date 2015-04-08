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
namespace Cake\ElasticSearch\Test;

use Cake\ElasticSearch\Document;
use Cake\TestSuite\TestCase;
use Elatica\Result;

/**
 * Tests the Document class
 *
 */
class DocumentTest extends TestCase
{

    /**
     * Tests constructing a document 
     *
     * @return void
     */
    public function testConstructorArray()
    {
        $data = ['foo' => 1, 'bar' => 2];
        $document = new Document($data);
        $this->assertSame($data, $document->toArray());
    }

    /**
     * Tests that constructing a document with a Elastica Result will
     * use the returned data out of it
     *
     * @return void
     */
    public function testConstructorWithResult()
    {
        $data = ['foo' => 1, 'bar' => 2];
        $result = $this->getMock('Elastica\Result', ['getData'], [[]]);
        $result->expects($this->once())->method('getData')
            ->will($this->returnValue($data));
        $document = new Document($result);
        $this->assertSame($data, $document->toArray());
    }
}
