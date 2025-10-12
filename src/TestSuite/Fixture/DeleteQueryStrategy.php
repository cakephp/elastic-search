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
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastica\Query\MatchAll;

class DeleteQueryStrategy implements FixtureStrategyInterface
{
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

                try {
                    $esIndex->deleteByQuery(new MatchAll());
                } catch (ClientResponseException $e) {
                    // Ignore version conflicts during test cleanup
                    // ElasticSearch 9.x is stricter about optimistic concurrency control
                    if ($e->getCode() !== 409) {
                        throw $e;
                    }

                    // For version conflicts, try to refresh and delete again
                    $esIndex->refresh();
                    try {
                        $esIndex->deleteByQuery(new MatchAll());
                    } catch (ClientResponseException $retryException) {
                        // If it still fails, ignore it - test cleanup should be resilient
                        if ($retryException->getCode() !== 409) {
                            throw $retryException;
                        }
                    }
                }

                $esIndex->refresh();
            }
        }, $this->fixtures);
    }
}
