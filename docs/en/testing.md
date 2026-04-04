# Testing

The ElasticSearch plugin includes test fixture support for loading mappings and records into a dedicated test connection.

## Loading Mappings

Generate and reload test mappings in `tests/bootstrap.php`:

```php
use Cake\ElasticSearch\TestSuite\Fixture\MappingGenerator;

$generator = new MappingGenerator('tests/mappings.php', 'test_elastic');
$generator->reload();
```

This creates the indexes and mappings defined in `tests/mappings.php` on the `test_elastic` connection.

Your mappings file should return an array of mappings:

```php
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
];
```

Mappings use the native Elasticsearch mapping format. You can omit the type name and the top-level `properties` key.

## Defining Fixtures

Create fixtures by extending `Cake\ElasticSearch\TestSuite\TestFixture`:

```php
namespace App\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ArticlesFixture extends TestFixture
{
    public string $table = 'articles';

    public array $records = [
        [
            'user' => [
                'username' => 'billy',
            ],
            'title' => 'First Post',
            'body' => 'Some content',
        ],
    ];
}
```

Use those fixtures in your test case as usual:

```php
public array $fixtures = ['app.Articles'];
```

::: info Schema handling
Before plugin `3.4.0`, schema was typically defined on each fixture using the `$schema` property. Current versions load mappings separately through `MappingGenerator`.
:::
