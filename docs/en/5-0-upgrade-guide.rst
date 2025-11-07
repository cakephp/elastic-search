5.0 Upgrade Guide
#################

.. warning::
    CakePHP ElasticSearch 5.x requires CakePHP 5.2+, ElasticSearch 9.x, and PHP 8.1+.

Requirements
============

* CakePHP 5.2+
* ElasticSearch 9.x
* Elastica 9.x (via ruflin/elastica)
* PHP 8.1+

Breaking Changes
================

Version 5.x includes several breaking changes to support ElasticSearch 9.x via
the Elastica 9.x library upgrade, along with compatibility updates for the latest
CakePHP 5.x features.

* Connection configuration format has changed to use ``hosts`` array.
* ``IndexRegistry`` class has been removed (was deprecated since 3.4.3).
* Several deprecated methods have been removed.
* Elastica API changes require updated client initialization.

Configuration Changes
=====================

Connection Configuration Format
--------------------------------

The recommended configuration format now uses a ``hosts`` array instead
of separate ``host`` and ``port`` keys. This aligns with Elastica 9.x
and provides better support for clustering.

Legacy configuration format (still supported)::

    // in config/app.php
    'Datasources' => [
        'elastic' => [
            'className' => 'Cake\ElasticSearch\Datasource\Connection',
            'driver' => 'Cake\ElasticSearch\Datasource\Connection',
            'host' => '127.0.0.1',
            'port' => 9200,
        ],
    ]

New configuration format (recommended)::

    // in config/app.php
    'Datasources' => [
        'elastic' => [
            'className' => 'Cake\ElasticSearch\Datasource\Connection',
            'driver' => 'Cake\ElasticSearch\Datasource\Connection',
            'hosts' => ['127.0.0.1:9200'],
        ],
    ]

For HTTPS endpoints::

    'hosts' => ['https://127.0.0.1:443']

Multiple hosts can be specified in the array::

    'hosts' => [
        '127.0.0.1:9200',
        '127.0.0.1:9201',
        '127.0.0.1:9202',
    ]

Deprecated Method Removals
===========================

Several deprecated methods have been removed in this version. Update your code
to use the replacement methods.

QueryBuilder Methods
--------------------

* ``QueryBuilder::and_()`` → use ``QueryBuilder::and()``
* ``QueryBuilder::or_()`` → use ``QueryBuilder::or()``

The underscore suffix is no longer needed as modern PHP allows these method names.

Old code::

    $query->where(function ($builder) {
        return $builder->and_(
            $builder->gt('views', 99),
            $builder->term('author.name', 'sally')
        );
    });

New code::

    $query->where(function ($builder) {
        return $builder->and(
            $builder->gt('views', 99),
            $builder->term('author.name', 'sally')
        );
    });

Embedded Association Methods
-----------------------------

* ``Embedded::property()`` → use ``setProperty()`` / ``getProperty()``
* ``Embedded::entityClass()`` → use ``setEntityClass()`` / ``getEntityClass()``
* ``Embedded::indexClass()`` → use ``setIndexClass()`` / ``getIndexClass()``

Old code::

    $association->property('comments');
    $class = $association->entityClass();
    $association->entityClass('App\Model\Document\Comment');

New code::

    $association->setProperty('comments');
    $class = $association->getEntityClass();
    $association->setEntityClass('App\Model\Document\Comment');

Query Methods
-------------

* ``Query::repository()`` → use ``setRepository()``

Old code::

    $query->repository($index);

New code::

    $query->setRepository($index);

.. note::
    ``Query::order()`` remains available as it's required by the
    ``Cake\Datasource\QueryInterface`` contract, but ``orderBy()`` is preferred.

IndexRegistry Removal
=====================

The deprecated ``IndexRegistry`` class has been completely removed. Use ``IndexLocator``
or ``IndexLocatorAwareTrait`` instead.

Old approach (no longer available)::

    use Cake\ElasticSearch\IndexRegistry;

    $articles = IndexRegistry::get('Articles');
    IndexRegistry::flush();

New approach using IndexLocator directly::

    use Cake\ElasticSearch\Datasource\IndexLocator;

    $locator = new IndexLocator();
    $articles = $locator->get('Articles');
    $locator->clear();

New approach using IndexLocatorAwareTrait::

    use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;

    class MyController extends Controller
    {
        use IndexLocatorAwareTrait;

        public function index()
        {
            $articles = $this->fetchIndex('Articles');
        }
    }

Alternative using FactoryLocator::

    use Cake\Datasource\FactoryLocator;

    $articles = FactoryLocator::get('ElasticSearch')->get('Articles');

Migration Steps
===============

1. **Update Dependencies**

   Update your ``composer.json`` to require version 5.x::

       composer require cakephp/elastic-search:^5.0

2. **Update Configuration (Optional but Recommended)**

   Consider updating your datasource configuration in ``config/app.php`` to use
   the ``hosts`` array format for better clustering support. The old ``host``
   and ``port`` format still works for backward compatibility.

3. **Update Code**

   Search your codebase for the deprecated methods and replace them:

   * Replace ``IndexRegistry`` usage with ``IndexLocator`` or ``IndexLocatorAwareTrait``
   * Replace ``and_()`` with ``and()``
   * Replace ``or_()`` with ``or()``
   * Replace getter/setter methods on embedded associations
   * Replace ``repository()`` with ``setRepository()``

4. **Test ElasticSearch 9.x Compatibility**

   Ensure your ElasticSearch cluster is running version 9.x and test your
   application thoroughly.

5. **Run Tests**

   Run your test suite to catch any compatibility issues::

       vendor/bin/phpunit
