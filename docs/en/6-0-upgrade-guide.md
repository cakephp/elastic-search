# 6.0 Upgrade Guide

::: warning Requirements
CakePHP ElasticSearch `6.x` requires CakePHP `6.0+`, Elasticsearch `9.x`, Elastica `9.x`, and PHP `8.4+`.
:::

## Requirements

- CakePHP `6.0+`
- Elasticsearch `9.x`
- Elastica `9.x`
- PHP `8.4+`

## Breaking Changes

Version `6.x` ports the plugin to CakePHP `6.x` and drops all CakePHP `5.x` support.
The Elasticsearch, Elastica and PHP version requirements are unchanged from `5.x`,
apart from the PHP version bump required by CakePHP `6`.

### Updated dependencies

Update your application requirements:

```bash
composer require cakephp/elastic-search:^6.0
```

- `cakephp/cakephp` is now `^6.0`.
- `php` is now `>=8.4`.

### Entity access rules

`Cake\Datasource\EntityTrait` renamed its access control API:

| CakePHP 5.x                     | CakePHP 6.x                     |
| ------------------------------- | ------------------------------- |
| `$entity->setAccess(...)`       | `$entity->setPatchable(...)`    |
| `$entity->getAccessible()`      | `$entity->getPatchable()`       |
| `$entity->isAccessible(...)`    | `$entity->isPatchable(...)`     |
| `'accessibleFields' => [...]`   | `'patchableFields' => [...]`    |

The `Marshaller` options array follows the same rename:

```php
// Before (5.x)
$entity = $this->Articles->marshallOne($data, ['accessibleFields' => ['title' => false]]);

// After (6.x)
$entity = $this->Articles->marshallOne($data, ['patchableFields' => ['title' => false]]);
```

### Query changes

- `Query::order()` has been removed. Use `Query::orderBy()` instead.
- Finders no longer accept a positional options array. Pass finder options as
  named arguments; passing an array now throws an `InvalidArgumentException`:

```php
// Before (5.x, deprecated)
$this->Articles->find('all', ['limit' => 10]);

// After (6.x)
$this->Articles->find('all', limit: 10);
```

- Fluent query methods (`select()`, `where()`, `limit()`, `offset()`, `page()`,
  `orderBy()`, `applyOptions()`, `setRepository()`, ...) now declare a `static`
  return type, as required by `Cake\Datasource\QueryInterface`.

### Fixtures

`Cake\Datasource\FixtureInterface::insert()` and `truncate()` now return `void`.
If you have custom fixtures that implement or extend `Cake\ElasticSearch\TestSuite\TestFixture`,
update their signatures accordingly:

```php
public function insert(ConnectionInterface $connection): void
{
}
```

### Subclassing `Cake\ElasticSearch\Document`

CakePHP `6` removed the underscore prefix convention. Plugin classes follow the
same rule, so protected members that custom subclasses may rely on were renamed:

| 5.x                  | 6.x              |
| -------------------- | ---------------- |
| `Document::$_result` | `Document::$searchResult` |

`$searchResult` holds the `Elastica\Result` the document was hydrated from and
backs `index()`, `version()`, `highlights()` and `explanation()`.

The same convention applies to `Query`, `Index`, `Connection`, `Marshaller` and
friends - for example `Query::$_queryParts` is now `Query::$queryParts`.

### Test suite

- `Index` and `Connection` fluent setters (`setConnection()`, `setName()`,
  `setEntityClass()`, `setCacher()`, `setLogger()`, `enableQueryLogging()`, ...)
  now declare a `static` return type.

## Recommended Migration Steps

1. Update `composer.json` to require `cakephp/elastic-search:^6.0` and run `composer update`.
2. Upgrade your application to CakePHP `6.0` and PHP `8.4` by following the
   [CakePHP 6 migration guide](https://book.cakephp.org/6.x/appendices/6-0-migration-guide.html).
3. Replace `setAccess()`/`getAccessible()`/`isAccessible()` calls with their
   `setPatchable()`/`getPatchable()`/`isPatchable()` equivalents.
4. Rename the `accessibleFields` marshaller option to `patchableFields`.
5. Replace `Query::order()` with `Query::orderBy()` and convert finder option
   arrays to named arguments.
6. Update custom fixture `insert()`/`truncate()` signatures to `void`.
7. Re-run your test suite against Elasticsearch `9.x`.
