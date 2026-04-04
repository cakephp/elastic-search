# 5.0 Upgrade Guide

::: warning Requirements
CakePHP ElasticSearch `5.x` requires CakePHP `5.2+`, Elasticsearch `9.x`, Elastica `9.x`, and PHP `8.1+`.
:::

## Requirements

- CakePHP `5.2+`
- Elasticsearch `9.x`
- Elastica `9.x`
- PHP `8.1+`

## Breaking Changes

Version `5.x` includes the breaking changes required for Elasticsearch `9.x`, the Elastica `9.x` upgrade, and current CakePHP `5.x` compatibility.

- Connection configuration now prefers a `hosts` array.
- `IndexRegistry` has been removed after being deprecated since `3.4.3`.
- Several deprecated methods have been removed.
- Elastica API updates require modern client initialization.

## Configuration Changes

### Connection Configuration Format

The recommended datasource format now uses `hosts` instead of separate `host` and `port` keys.

Legacy configuration format:

```php
'Datasources' => [
    'elastic' => [
        'className' => 'Cake\ElasticSearch\Datasource\Connection',
        'driver' => 'Cake\ElasticSearch\Datasource\Connection',
        'host' => '127.0.0.1',
        'port' => 9200,
    ],
]
```

Recommended configuration format:

```php
'Datasources' => [
    'elastic' => [
        'className' => 'Cake\ElasticSearch\Datasource\Connection',
        'driver' => 'Cake\ElasticSearch\Datasource\Connection',
        'hosts' => ['127.0.0.1:9200'],
    ],
]
```

For HTTPS endpoints:

```php
'hosts' => ['https://127.0.0.1:443']
```

For clusters with multiple nodes:

```php
'hosts' => [
    '127.0.0.1:9200',
    '127.0.0.1:9201',
    '127.0.0.1:9202',
]
```

## Deprecated Method Removals

### QueryBuilder Methods

- `QueryBuilder::and_()` -> `QueryBuilder::and()`
- `QueryBuilder::or_()` -> `QueryBuilder::or()`

Old code:

```php
$query->where(function ($builder) {
    return $builder->and_(
        $builder->gt('views', 99),
        $builder->term('author.name', 'sally')
    );
});
```

New code:

```php
$query->where(function ($builder) {
    return $builder->and(
        $builder->gt('views', 99),
        $builder->term('author.name', 'sally')
    );
});
```

### Embedded Association Methods

- `Embedded::property()` -> `setProperty()` / `getProperty()`
- `Embedded::entityClass()` -> `setEntityClass()` / `getEntityClass()`
- `Embedded::indexClass()` -> `setIndexClass()` / `getIndexClass()`

Old code:

```php
$association->property('comments');
$class = $association->entityClass();
$association->entityClass('App\Model\Document\Comment');
```

New code:

```php
$association->setProperty('comments');
$class = $association->getEntityClass();
$association->setEntityClass('App\Model\Document\Comment');
```

### Query Methods

- `Query::repository()` -> `setRepository()`

Old code:

```php
$query->repository($index);
```

New code:

```php
$query->setRepository($index);
```

::: tip Ordering
`Query::order()` still exists because it is part of `Cake\Datasource\QueryInterface`, but `orderBy()` is preferred.
:::

## IndexRegistry Removal

Use `IndexLocator`, `IndexLocatorAwareTrait`, or `FactoryLocator` instead of `IndexRegistry`.

Old approach:

```php
use Cake\ElasticSearch\IndexRegistry;

$articles = IndexRegistry::get('Articles');
IndexRegistry::flush();
```

New approach with `IndexLocator`:

```php
use Cake\ElasticSearch\Datasource\IndexLocator;

$locator = new IndexLocator();
$articles = $locator->get('Articles');
$locator->clear();
```

New approach with `IndexLocatorAwareTrait`:

```php
use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;

class MyController extends Controller
{
    use IndexLocatorAwareTrait;

    public function index()
    {
        $articles = $this->fetchIndex('Articles');
    }
}
```

Alternative with `FactoryLocator`:

```php
use Cake\Datasource\FactoryLocator;

$articles = FactoryLocator::get('ElasticSearch')->get('Articles');
```

## Migration Steps

1. Update dependencies to `cakephp/elastic-search:^5.0`.
2. Move datasource configuration to the `hosts` array format if you still use `host` and `port`.
3. Replace removed APIs such as `IndexRegistry`, `and_()`, `or_()`, embedded association setters/getters, and `repository()`.
4. Verify your Elasticsearch cluster is running `9.x`.
5. Run your application test suite.

```bash
vendor/bin/phpunit
```
