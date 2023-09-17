ElasticSearch
#############

The ElasticSearch plugin provides an ORM-like abstraction on top of
`elasticsearch <https://www.elastic.co/products/elasticsearch>`_. The plugin
provides features that make testing, indexing documents and searching your
indexes easier.

Installation
============

To install the ElasticSearch plugin, you can use ``composer``. From your
application's ROOT directory (where ``composer.json`` file is located) run the
following::

    php composer.phar require cakephp/elastic-search "^4.0"

You will need to add the following line to your application's
**src/Application.php** file::

    $this->addPlugin('Cake/ElasticSearch');

Additionally, you will need to configure the 'elastic' datasource connection in
your **config/app.php** file. An example configuration would be::

    // in config/app.php
    'Datasources' => [
        // other datasources
        'elastic' => [
            'className' => 'Cake\ElasticSearch\Datasource\Connection',
            'driver' => 'Cake\ElasticSearch\Datasource\Connection',
            'host' => '127.0.0.1',
            'port' => 9200,
            'index' => 'my_apps_index',
        ],
    ]

If your endpoint requires https, use::

    'port' => 443,
    'transport' => 'https'

or you might get a 400 response back from the elasticsearch server.

Overview
========

The ElasticSearch plugin makes it easier to interact with an elasticsearch index
and provides an interface similar to the `ORM
<https://book.cakephp.org/3/en/orm.html>`__. To get started you should
create an ``Index`` object. ``Index`` objects are the "Repository" or table-like
class in elasticsearch::

    // in src/Model/Type/ArticlesIndex.php
    namespace App\Model\Index;

    use Cake\ElasticSearch\Index;

    class ArticlesIndex extends Index
    {
    }

You can then use your index class in your controllers::

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        // Load the Index using the 'Elastic' provider.
        $this->loadModel('Articles', 'Elastic');
    }

    public function add()
    {
        $article = $this->Articles->newEntity();
        if ($this->request->is('post')) {
            $article = $this->Articles->patchEntity($article, $this->request->getData());
            if ($this->Articles->save($article)) {
                $this->Flash->success('It saved');
            }
        }
        $this->set(compact('article'));
    }

We would also need to create a basic view for our indexed articles::

    // in src/Template/Articles/add.ctp
    <?= $this->Form->create($article) ?>
    <?= $this->Form->control('title') ?>
    <?= $this->Form->control('body') ?>
    <?= $this->Form->button('Save') ?>
    <?= $this->Form->end() ?>

You should now be able to submit the form and have a new document added to
elasticsearch.

Document Objects
================

Like the ORM, the Elasticsearch ODM uses ORM-like classes. The
base class you should inherit from is ``Cake\ElasticSearch\Document``. Document
classes are found in the ``Model\Document`` namespace in your application or
plugin::

    namespace App\Model\Document;

    use Cake\ElasticSearch\Document;

    class Article extends Document
    {
    }

Outside of constructor logic that makes Documents work with data from
elasticsearch, the interface and functionality provided by ``Document`` are the
same as those in `Entities
<https://book.cakephp.org/3.0/en/orm/entities.html>`__

Searching Indexed Documents
===========================

After you've indexed some documents you will want to search through them. The
ElasticSearch plugin provides a query builder that allows you to build search
queries::

    $query = $this->Articles->find()
        ->where([
            'title' => 'special',
            'or' => [
                'tags in' => ['cake', 'php'],
                'tags not in' => ['c#', 'java']
            ]
        ]);

    foreach ($query as $article) {
        echo $article->title;
    }

You can use the ``QueryBuilder`` to add filtering conditions::

    $query->where(function ($builder) {
        return $builder->and(
            $builder->gt('views', 99),
            $builder->term('author.name', 'sally')
        );
    });

The `QueryBuilder source
<https://github.com/cakephp/elastic-search/blob/master/src/QueryBuilder.php>`_
has the complete list of methods with examples for many commonly used methods.

Validating Data & Using Application Rules
=========================================

Like the ORM, the ElasticSearch plugin lets you validate data when marshalling
documents. Validating request data, and applying application rules works the
same as it does with the relational ORM. See the `validating request data
<https://book.cakephp.org/3.0/en/orm/validation.html#validating-data-before-building-entities>`__
and `Application Rules
<https://book.cakephp.org/3.0/en/orm/validation.html#applying-application-rules>`__
sections for more information.

.. Need information on nested validators.

Saving New Documents
====================

When you're ready to index some data into elasticsearch, you'll first need to
convert your data into a ``Document`` that can be indexed::

    $article = $this->Articles->newEntity($data);
    if ($this->Articles->save($article)) {
        // Document was indexed
    }

When marshalling a document, you can specify which embedded documents you wish
to marshall using the ``associated`` key::

    $article = $this->Articles->newEntity($data, ['associated' => ['Comments']]);

Saving a document will trigger the following events:

* ``Model.beforeSave`` - Fired before the document is saved. You can prevent the
  save operation from happening by stopping this event.
* ``Model.buildRules`` - Fired when the rules checker is built for the first
  time.
* ``Model.afterSave`` - Fired after the document is saved.

.. note::
    There are no events for embedded documents, as the parent document and all
    of its embedded documents are saved as one operation.

Updating Existing Documents
===========================

When you need to re-index data, you can patch existing entities and re-save
them::

    $query = $this->Articles->find()->where(['user.name' => 'jill']);
    foreach ($query as $doc) {
        $doc->set($newProperties);
        $this->Articles->save($doc);
    }

Additionally Elasticsearch ``refresh`` request can be triggered by passing
``'refresh' => true`` in the ``$options`` argument. A refresh makes recent
operations performed on one or more indices available for search::

    $this->Articles->save($article, ['refresh' => true]);

Saving Multiple Documents
=========================

Using this method you can bulk save multiple documents::

    $result = $this->Articles->saveMany($documents);

Here ``$documents`` is an array of documents. The result will be ``true`` on success or ``false`` on failure.
``saveMany`` can have second argument with the same options as accepted by ``save()``.


Deleting Documents
==================

After retrieving a document you can delete it::

    $doc = $this->Articles->get($id);
    $this->Articles->delete($doc);

You can also delete documents matching specific conditions::

    $this->Articles->deleteAll(['user.name' => 'bob']);

Embedding Documents
===================

By defining embedded documents, you can attach entity classes to specific
property paths in your documents. This allows you to provide custom behavior to
the documents within a parent document. For example, you may want the comments
embedded in an article to have specific application specific methods. You can
use ``embedOne`` and ``embedMany`` to define embedded documents::

    // in src/Model/Index/ArticlesIndex.php
    namespace App\Model\Index;

    use Cake\ElasticSearch\Index;

    class ArticlesIndex extends Index
    {
        public function initialize()
        {
            $this->embedOne('User');
            $this->embedMany('Comments', [
                'entityClass' => 'MyComment'
            ]);
        }
    }

The above would create two embedded documents on the ``Article`` document. The
``User`` embed will convert the ``user`` property to instances of
``App\Model\Document\User``. To get the Comments embed to use a class name
that does not match the property name, we can use the ``entityClass`` option to
configure a custom class name.

Once we've setup our embedded documents, the results of ``find()`` and ``get()``
will return objects with the correct embedded document classes::

    $article = $this->Articles->get($id);
    // Instance of App\Model\Document\User
    $article->user;

    // Array of App\Model\Document\Comment instances
    $article->comments;

Configuring Connections
=======================

By default all index instances use the ``elastic`` connection. If your
application uses multiple connections you will want to configure which
index use which connections. This is the ``defaultConnectionName()`` method::

    namespace App\Model\Index;

    use Cake\ElasticSearch\Index;

    class ArticlesIndex extends Index
    {
        public static function defaultConnectionName() {
            return 'replica_db';
        }
    }

.. note::

    The ``defaultConnectionName()`` method **must** be static.

Getting Index Instances
=======================

Like the ORM, the ElasticSearch plugin provides a factory/registry for getting
``Index`` instances::

    use Cake\ElasticSearch\IndexRegistry;

    $articles = IndexRegistry::get('Articles');

Flushing the Registry
---------------------

During test cases you may want to flush the registry. Doing so is often useful
when you are using mock objects, or modifying a index's dependencies::

    IndexRegistry::flush();

Test Fixtures
=============

The ElasticSearch plugin provides a seamless test suite integration. Just like
database fixtures, you can create test schema and fixture data elasticsearch.
Much like database fixtures we load our Elasticsearch mappings during
``tests/bootstrap.php`` of our application::

    // In tests/bootstrap.php
    use Cake\Elasticsearch\TestSuite\Fixture\MappingGenerator;

    $generator = new MappingGenerator('tests/mappings.php', 'test_elastic');
    $generator->reload();

The above will create the indexes and mappings defined in ``tests/mapping.php``
and insert them into the ``test_elastic`` connection. The mappings in your
``mappings.php`` should return a list of mappings to create::

    // in tests/mappings.php
    return [
        [
            // The name of the index and mapping.
            'name' => 'articles',
            // The schema for the mapping.
            'mapping' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'text'],
                'user_id' => ['type' => 'integer'],
                'body' => ['type' => 'text'],
                'created' => ['type' => 'date'],
            ],
            // Additional index settings.
            'settings' => [
                'number_of_shards' => 2,
                'number_of_routing_shards' => 2,
            ],
        ],
        // ...
    ];

Mappings use the `native elasticsearch mapping format
<https://www.elastic.co/guide/en/elasticsearch/reference/1.5/mapping.html>`_.
You can safely omit the type name and top level ``properties`` key.  With our
mappings loaded, we can define a test fixture for our Articles index with the
following::

    namespace App\Test\Fixture;

    use Cake\ElasticSearch\TestSuite\TestFixture;

    /**
     * Articles fixture
     */
    class ArticlesFixture extends TestFixture
    {
        /**
         * The table/index for this fixture.
         *
         * @var string
         */
        public $table = 'articles';

        public $records = [
            [
                'user' => [
                    'username' => 'billy'
                ],
                'title' => 'First Post',
                'body' => 'Some content'
            ]
        ];
    }

.. versionchanged:: 3.4.0
    Prior to CakePHP 4.3.0 schema was defined on each fixture in the ``$schema``
    property.

Once your fixtures are created you can use them in your test cases by including
them in your test's ``fixtures`` properties::

    public $fixtures = ['app.Articles'];
