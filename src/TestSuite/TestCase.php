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

namespace Cake\ElasticSearch\TestSuite;

use Cake\ElasticSearch\TestSuite\Fixture\DeleteQueryStrategy;
use Cake\TestSuite\Fixture\FixtureStrategyInterface;
use Cake\TestSuite\TestCase as CakeTestCase;

/**
 * Elastic-search TestCase that uses DeleteQueryStrategy.
 */
class TestCase extends CakeTestCase
{
    /**
     * @inheritDoc
     */
    public function getFixtureStrategy(): FixtureStrategyInterface
    {
        return new DeleteQueryStrategy();
    }
}
