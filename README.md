# Elasticsearch Datasource for CakePHP

[![Build Status](https://travis-ci.org/cakephp/elastic-search.svg?branch=master)](https://travis-ci.org/cakephp/elastic-search)
[![License](https://poser.pugx.org/cakephp/elastic-search/license.svg)](https://packagist.org/packages/cakephp/elastic-search)

Use [Elastic Search](https://www.elastic.co/) as an alternative ORM backend in CakePHP 3.6+. It is currently under active development.

You can [find the documentation for the plugin in the Cake Book](http://book.cakephp.org/3.0/en/elasticsearch.html).

## Installing Elasticsearch via composer

You can install Elasticsearch into your project using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

    "require": {
        "cakephp/elastic-search": "^2.0-RC1"
    }

And run `php composer.phar update`

Please use the 1.x branch if you are looking for a version of this plugin that is compatible with Cake versions prior to 3.6 and ES versions before 6.x (2.x and 5.x)

## Connecting the Plugin to your Application

After installing, you should tell your application to load the plugin:

```php
use Cake\ElasticSearch\Plugin as ElasticSearchPlugin;

class Application extends BaseApplication
{
    public function bootstrap()
    {
        $this->addPlugin(ElasticSearchPlugin::class);

        // If you want to disable to automatically configure the Elastic model provider
        // and FormHelper do the following:
        // $this->addPlugin(ElasticSearchPlugin::class, [ 'bootstrap' => false ]);
    }
}
```

## Defining a connection

Before you can do any work with Elasticsearch models, you'll need to define
a connection:

```php
// in config/app.php
    'Datasources' => [
        // other datasources
        'elastic' => [
            'className' => 'Cake\ElasticSearch\Datasource\Connection',
            'driver' => 'Cake\ElasticSearch\Datasource\Connection',
            'host' => '127.0.0.1',
            'port' => 9200
        ],
    ]
```
As an alternative you could use a link format if you like to use enviroment variables for example.

```php
// in config/app.php
    'Datasources' => [
        // other datasources
        'elastic' => [
            'url' => env('ELASTIC_URL', null)
        ]
    ]

    // and make sure the folowing env variable is available:
    // ELASTIC_URL="Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?driver=Cake\ElasticSearch\Datasource\Connection"
```

You can enable request logging by setting the `log` config option to true. By
default the `debug` Log profile will be used. You can also
define an `elasticsearch` log profile in `Cake\Log\Log` to customize where
Elasticsearch query logs will go. Query logging is done at a 'debug' level.

## Getting a Index object

Index objects are the equivalent of `ORM\Table` instances in elastic search. You can
use the `IndexRegistry` factory to get instances, much like `TableRegistry`:

```php
use Cake\ElasticSearch\IndexRegistry;

$comments = IndexRegistry::get('Comments');
```

If you have loaded the plugin with bootstrap enabled you could load indexes using the model factory in your controllers
```php
class SomeController extends AppController
{
    public function initialize()
    {
        $this->loadModel('Comments', 'Elastic');
    }

    public function index()
    {
        $comments = $this->Comments->find();
    }

    ...
```

Each `Index` object needs a correspondent Elasticsearch _index_, just like most of `ORM\Table` needs a database _table_.

In the above example, if you have defined a class as `CommentsIndex` and the `IndexRegistry` can find it, the `$comments` will receive a initialized object with inner configurations of connection and index. But if you don't have that class, a default one will be initialized and the index name on Elasticsearch mapped to the class.

## Defining a Index class

Creating your own `Index` allows you to define the name of internal _index_ for Elasticsearch, and it mapping type. As you have to [use only one mapping type for each _index_](https://www.elastic.co/guide/en/elasticsearch/reference/master/removal-of-types.html), you can use the same name for both (this is the default behavior when _type_ is undefined). Index types will be removed from ES 7 and up.

```php
use Cake\ElasticSearch\Index;

class CommentsIndex extends Index
{
    /**
     * The name of index in Elasticsearch
     *
     * @return  string
     */
    public function getName()
    {
        return 'comments';
    }

    /**
     * The name of mapping type in Elasticsearch
     *
     * @return  string
     */
    public function getType()
    {
        return 'comments';
    }
}
```

## Running tests

**Warning**: Please, be very carefully when running tests as the Fixture will
create and drop Elasticsearch indexes for its internal structure. Don't run tests
in production or development machines where you have important data into your
Elasticsearch instance.

Assuming you have PHPUnit installed system wide using one of the methods stated
[here](http://phpunit.de/manual/current/en/installation.html), you can run the
tests for CakePHP by doing the following:

1. Copy `phpunit.xml.dist` to `phpunit.xml`
2. Run `phpunit`
