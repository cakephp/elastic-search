# Elasticsearch Datasource for CakePHP

[![Build Status](https://api.travis-ci.org/cakephp/elastic-search.png)](https://travis-ci.org/cakephp/elastic-search)
[![License](https://poser.pugx.org/cakephp/elastic-search/license.svg)](https://packagist.org/packages/cakephp/elastic-search)

This is a pre-alpha version of an alternative ORM for CakePHP 3.0 using [Elastic Search](https://www.elastic.co/)
as its backend. It is currently under development and is only being used to test the
interfaces exposed in CakePHP 3.0.

You can [find the documentation for the plugin in the Cake Book](http://book.cakephp.org/3.0/en/elasticsearch.html).

## Installing Elasticsearch via composer

You can install Elasticsearch into your project using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

    "require": {
        "cakephp/elastic-search": "^1.0"
    }

And run `php composer.phar update`

## Connecting the Plugin to your Application

After installing, you should tell your application to load the plugin:

```php
// in config/bootstrap.php
Plugin::load('Cake/ElasticSearch');

// If you want the plugin to automatically configure the Elastic model provider
// and FormHelper do the following:
Plugin::load('Cake/ElasticSearch', ['bootstrap' => true]);
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

You can enable request logging by setting the `log` config option to true. By
default, `Elastica\Log` will be used, which logs via `error_log`. You can also
define an `elasticsearch` log profile in `Cake\Log\Log` to customize where
Elasticsearch query logs will go. Query logging is done at a 'debug' level.

## Getting a Index object

Index objects are the equivalent of `ORM\Table` instances in elastic search. You can
use the `IndexRegistry` factory to get instances, much like `TableRegistry`:

```php
use Cake\ElasticSearch\IndexRegistry;

$comments = IndexRegistry::get('Comments');
```

Each `Index` object need a correspondent Elasticsearch _index_, just like most of `ORM\Table` needs a database _table_.

In the above example, if you have defined a class as `CommentsIndex` and the `IndexRegistry` can found it, the `$comments` will receive a initialized object with inner configurations of connection and index. But if you don't have that class, a default one will be initialized and the index name on Elasticsearch mapped to the class.

## Defining a Index class

Creating your own `Index` allow you to define name of internal _index_ of  Elasticsearch, and it mapping type. As you has to [use only one mapping type for each _index_](https://www.elastic.co/guide/en/elasticsearch/reference/master/removal-of-types.html), you can use the same name for both (this is the default behavior when _type_ is undefined).

```php
use Cake\ElasticSearch\Index;

class CommentsIndex extends Index
{
    /**
     * The name of index in Elasticsearch
     *
     * @type string
     */
    public $name = 'comments';

    /**
     * The name of mapping type in Elasticsearch
     *
     * @type string
     */
    public $type = 'comments';
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
