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
 * @since         4.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\TestSuite\Fixture;

use Cake\ElasticSearch\IndexRegistry;
use Cake\ElasticSearch\TestSuite\Fixture\DeleteQueryStrategy;
use Cake\TestSuite\TestCase;

/**
 * Test for DeleteQueryStrategy
 */
class DeleteQueryStrategyTest extends TestCase
{
    /**
     * Fixture list
     *
     * @var string[]
     */
    public $fixtures = ['plugin.Cake/ElasticSearch.Articles'];

    /**
     * Test teardown
     *
     * @return void
     */
    public function testTeardownTest()
    {
        $articleIndex = IndexRegistry::get('Articles');

        $strategy = new DeleteQueryStrategy($this->fixtureManager);
        $this->assertCount(2, $articleIndex->find()->all());

        $strategy->teardownTest();
        $this->assertCount(0, $articleIndex->find()->all());
    }
}
