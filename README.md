# Elasticsearch Datasource for CakePHP

[![Build Status](https://img.shields.io/travis/com/cakephp/elastic-search?style=flat-square)](https://travis-ci.com/cakephp/elastic-search)
[![Latest Stable Version](https://img.shields.io/github/v/release/cakephp/elastic-search?sort=semver&style=flat-square)](https://packagist.org/packages/cakephp/elastic-search)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/elastic-search?style=flat-square)](https://packagist.org/packages/cakephp/elastic-search/stats)
[![Code Coverage](https://img.shields.io/coveralls/cakephp/elastic-search/master.svg?style=flat-square)](https://coveralls.io/r/cakephp/elastic-search?branch=master)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Use [Elastic Search](https://www.elastic.co/) as an alternative ORM backend in CakePHP 4.0+.

You can [find the documentation for the plugin in the Cake Book](http://book.cakephp.org/elasticsearch).

## Installing Elasticsearch via composer

You can install Elasticsearch into your project using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

    "require": {
        "cakephp/elastic-search": "^3.0"
    }

And run `php composer.phar update`

### Versions Table

| Cake\ElasticSearch | CakePHP | ElasticSearch |
| --- | --- | --- |
| [1.x](https://github.com/cakephp/elastic-search/tree/1.0) | 3.0 - 3.5 | 2.x - 5.x |
| [2.x](https://github.com/cakephp/elastic-search/tree/2.x) | 3.6+ | 6.x |
| [3.x](https://github.com/cakephp/elastic-search/tree/master) | 4.0+ | 6.x |

You are seeing the 3.x version.

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

## The Index class

You must create your own `Index` class to define the name of internal _index_
for Elasticsearch, as well as to define the mapping type and define any entity
properties you need like virtual properties. As you have to
[use only one mapping type for each _index_](https://www.elastic.co/guide/en/elasticsearch/reference/master/removal-of-types.html),
you can use the same name for both (the default behavior when _type_ is
undefined is use singular version of _index_ name). Index types were removed
in ElasticSearch 7.

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
