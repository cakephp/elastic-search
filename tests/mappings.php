<?php
declare(strict_types=1);

return [
    [
        'name' => 'articles',
        'mapping' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'text'],
            'user_id' => ['type' => 'integer'],
            'body' => ['type' => 'text'],
            'created' => ['type' => 'date'],
        ],
        'settings' => [
            'number_of_shards' => 2,
            'number_of_routing_shards' => 2,
        ],
    ],
    [
        'name' => 'profiles',
        'mapping' => [
            'id' => ['type' => 'integer'],
            'username' => ['type' => 'text'],
            'address' => [
                'type' => 'nested',
                'properties' => [
                    'street' => ['type' => 'text'],
                    'city' => ['type' => 'text'],
                    'province' => ['type' => 'text'],
                    'country' => ['type' => 'text'],
                ],
            ],
        ],
    ],
];
