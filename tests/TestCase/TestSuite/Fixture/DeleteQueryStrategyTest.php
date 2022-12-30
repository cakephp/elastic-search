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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Test\TestCase\TestSuite\Fixture;

use Cake\ElasticSearch\IndexRegistry;
use Cake\ElasticSearch\TestSuite\Fixture\DeleteQueryStrategy;
use Cake\ElasticSearch\TestSuite\TestCase;

/**
 * Test for DeleteQueryStrategy
 */
class DeleteQueryStrategyTest extends TestCase
{
    /**
     * Test teardown
     *
     * @return void
     */
    public function testTeardownTest()
    {
        $articleIndex = IndexRegistry::get('Articles');

        $strategy = new DeleteQueryStrategy();
        $strategy->setupTest(['plugin.Cake/ElasticSearch.Articles']);
        $this->assertCount(2, $articleIndex->find()->all());

        $strategy->teardownTest();
        $this->assertCount(0, $articleIndex->find()->all());
    }
}
