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
 * Article test fixture.
 */
class ArticlesFixture extends TestFixture
{
    /**
     * The table/type for this fixture.
     *
     * @var string
     */
    public $table = 'articles';

    /**
     * The mapping data.
     *
     * @var array
     */
    public $schema = [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'text'],
        'user_id' => ['type' => 'integer'],
        'body' => ['type' => 'text'],
        'created' => ['type' => 'date'],
    ];

    /**
     * The fixture records
     *
     * @var array
     */
    public $records = [
        [
            'id' => '1',
            'title' => 'First article',
            'user_id' => 1,
            'body' => 'A big box of bolts and nuts.',
            'created' => '2014-04-01T15:01:30',
        ],
        [
            'id' => '2',
            'title' => 'Second article',
            'user_id' => 2,
            'body' => 'A delicious cake I made yesterday for you.',
            'created' => '2015-04-06T16:03:30',
        ],
    ];
}
