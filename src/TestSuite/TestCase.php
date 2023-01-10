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
