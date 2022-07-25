4.0 Upgrade Guide
#################

.. warning::
    CakePHP ElasticSearch 4.x requires CakePHP 5.x.

Breaking Changes
================

* All methods have had native types added where possible. This improves type
  safety within this plugin but may cause errors with application code.
* ``Query`` no longer uses ``Cake\ORM\QueryTrait``. This has allowed several
  unused methods to be removed.
* ``Query::isEagerLoaded()``, and ``Query::eagerLoaded()`` were removed.
  Previously these methods were inherited from ``QueryTrait`` but served no
  purpose here.
