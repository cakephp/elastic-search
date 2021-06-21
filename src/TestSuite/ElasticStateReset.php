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

use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\IndexRegistry;
use Cake\TestSuite\Fixture\FixtureLoader;
use Cake\TestSuite\Fixture\StateResetStrategyInterface;
use Elastica\Query\MatchAll;

class ElasticStateReset implements StateResetStrategyInterface
{
    /**
     * @var \Cake\TestSuite\Fixture\FixtureLoader
     */
    protected $loader;

    /**
     * Constructor.
     *
     * @param \Cake\TestSuite\Fixture\FixtureLoader $loader The fixture loader being used.
     * @return void
     */
    public function __construct(FixtureLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * No-op implemented for interface.
     *
     * @return void
     */
    public function setupTest(): void
    {
    }

    /**
     * Clear state in all elastic indexes.
     *
     * @return void
     */
    public function teardownTest(): void
    {
        $connections = ConnectionManager::configured();
        foreach ($connections as $name) {
            if (strpos($name, 'test') !== 0) {
                continue;
            }
            $db = ConnectionManager::get($name);
            if ($db instanceof Connection) {
                $loaded = $this->loader->getInserted();
                foreach ($loaded as $fixture) {
                    try {
                        $index = IndexRegistry::get($fixture);
                    } catch (\Exception $e) {
                        // Fixture is likely not an elastic search one.
                        continue;
                    }

                    $query = new MatchAll();
                    $esIndex = $db->getIndex($index->getName());
                    $esIndex->deleteByQuery($query);
                    $esIndex->refresh();
                }
            }
        }
    }
}
