4.0 Upgrade Guide
#################

If you are upgrading from an earlier version of this plugin, this page aims to
collect all the changes you may need to make to your application while
upgrading.

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
