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
namespace Cake\ElasticSearch\TestSuite\Fixture;

use Cake\Datasource\ConnectionInterface;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\TestSuite\Fixture\FixtureHelper;
use Cake\TestSuite\Fixture\FixtureStrategyInterface;
use Elastica\Query\MatchAll;

class DeleteQueryStrategy implements FixtureStrategyInterface
{
    /**
     * @var \Cake\TestSuite\Fixture\FixtureHelper
     */
    protected FixtureHelper $helper;

    /**
     * @var array<\Cake\Datasource\FixtureInterface>
     */
    protected array $fixtures = [];

    /**
     * Initialize strategy.
     */
    public function __construct()
    {
        $this->helper = new FixtureHelper();
    }

    /**
     * @inheritDoc
     */
    public function setupTest(array $fixtureNames): void
    {
        $this->fixtures = $this->helper->loadFixtures($fixtureNames);
        $this->helper->runPerConnection(function (ConnectionInterface $connection, array $fixtures): void {
            if (!$connection instanceof Connection) {
                return;
            }

            foreach ($fixtures as $fixture) {
                $fixture->insert($connection);
            }
        }, $this->fixtures);
    }

    /**
     * Clear state in all elastic indexes.
     *
     * @return void
     */
    public function teardownTest(): void
    {
        $this->helper->runPerConnection(function (ConnectionInterface $connection, array $fixtures): void {
            if (!$connection instanceof Connection) {
                return;
            }

            /** @var \Cake\ElasticSearch\TestSuite\TestFixture $fixture */
            foreach ($fixtures as $fixture) {
                /** @var \Cake\ElasticSearch\Datasource\Connection $connection */
                $esIndex = $connection->getIndex($fixture->getIndex()->getName());
                $esIndex->deleteByQuery(new MatchAll());
                $esIndex->refresh();
            }
        }, $this->fixtures);
    }
}
