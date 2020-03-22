3.0 Upgrade Guide
#################

If you are upgrading from an earlier version of this plugin, this page aims to
collect all the changes you may need to make to your application while
upgrading.

Types Renamed to Indexes
========================

Because of the changes made in elasticsearch 5 and 6, this plugin no longer
supports multiple types in the same index. The impact of this is that all of
your type classes need to be renamed to indexes. For example
``App\Model\Type\ArticlesType`` needs to become
``App\Model\Index\ArticlesIndex``. Furthermore, Index classes assume that the
type mapping has the singular name of the index. For example the ``articles``
index has a type mapping of ``article``.

Breaking Changes
================

* ``Index::entityClass()`` was removed. Use ``getEntityClass()`` or
  ``setEntityClass()`` instead.
* ``ResultSet::hasFacets()`` was removed as elastica no longer exposes this
  method.
* ``ResultSet::getFacets()`` was removed as elastica no longer exposes this
  method.
* ``ResultSet::getFacets()`` was removed as elastica no longer exposes this
  method.
* The ``Type`` base class is now ``Index``.
* ``TypeRegistry`` is now ``IndexRegistry``.
