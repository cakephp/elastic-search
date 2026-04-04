# 4.0 Upgrade Guide

::: warning Requirements
CakePHP ElasticSearch `4.x` requires CakePHP `5.x`.
:::

## Breaking Changes

- Native PHP types were added where possible. This improves type safety but may expose incompatibilities in application code.
- `Query` no longer uses `Cake\ORM\QueryTrait`, which removed several inherited methods that were never used by this plugin.
- `Query::isEagerLoaded()` and `Query::eagerLoaded()` were removed.

## Indexes

`IndexRegistry` was deprecated in `4.x`.

Old code:

```php
use Cake\ElasticSearch\IndexRegistry;

$articles = IndexRegistry::get('Articles');
```

New code:

```php
use Cake\Datasource\FactoryLocator;

$articles = FactoryLocator::get('ElasticSearch')->get('Articles');
```
