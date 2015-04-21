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
namespace Cake\ElasticSearch\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

/**
 * Profile & Address test fixture.
 */
class ProfilesFixture extends TestFixture
{
    /**
     * The table/type for this fixture.
     *
     * @var string
     */
    public $table = 'profiles';

    /**
     * The mapping data.
     *
     * @var array
     */
    public $schema = [
        'id' => ['type' => 'integer'],
        'username' => ['type' => 'string'],
        'address' => [
            'type' => 'nested',
            'properties' => [
                'street' => ['type' => 'string'],
                'city' => ['type' => 'string'],
                'province' => ['type' => 'string'],
                'country' => ['type' => 'string'],
            ]
        ],
    ];

    /**
     * The fixture records
     *
     * @var array
     */
    public $records = [
        [
            'id' => '1',
            'username' => 'mark',
            'address' => [
                'street' => '123 street',
                'city' => 'Toronto',
                'province' => 'Ontario',
                'country' => 'Canada'
            ]
        ],
        [
            'id' => '2',
            'username' => 'jose',
            'address' => [
                'street' => '456 street',
                'city' => 'Copenhagen',
                'province' => 'Copenhagen',
                'country' => 'Denmark'
            ]
        ],
        [
            'id' => '3',
            'username' => 'sara',
            'address' => [
                [
                    'street' => '456 street',
                    'city' => 'Copenhagen',
                    'province' => 'Copenhagen',
                    'country' => 'Denmark'
                ],
                [
                    'street' => '89 street',
                    'city' => 'Calgary',
                    'province' => 'Alberta',
                    'country' => 'Canada'
                ]
            ]
        ],
    ];
}
