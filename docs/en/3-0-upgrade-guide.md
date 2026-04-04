# 3.0 Upgrade Guide

If you are upgrading from an earlier version of this plugin, this page collects the application changes you may need to make.

## Types Renamed to Indexes

Because of Elasticsearch 5 and 6 changes, the plugin no longer supports multiple types in the same index. All of your type classes therefore need to be renamed to indexes.

For example:

- `App\Model\Type\ArticlesType` becomes `App\Model\Index\ArticlesIndex`
- `Type` base classes become `Index` base classes

Index classes assume that the mapping type uses the singularized index name. For example, the `articles` index uses the `article` mapping type.

## Breaking Changes

- `Index::entityClass()` was removed. Use `getEntityClass()` or `setEntityClass()` instead.
- `ResultSet::hasFacets()` was removed because Elastica no longer exposes this method.
- `ResultSet::getFacets()` was removed because Elastica no longer exposes this method.
- `Type` is now `Index`.
- `TypeRegistry` is now `IndexRegistry`.
