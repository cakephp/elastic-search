# ElasticSearch Datasource for CakePHP

[![Build Status](https://api.travis-ci.org/cakephp/elastic-search.png)](https://travis-ci.org/cakephp/elastic-search)
[![License](https://poser.pugx.org/cakephp/elastic-search/license.svg)](https://packagist.org/packages/cakephp/elastic-search)

This is a pre-alpha version of an alternative ORM for CakePHP 3.0 using [Elastic Search](http://www.elasticsearch.org/)
as its backend. It is currently under development and is only being used to test the
interfaces exposed in CakePHP 3.0.

You can [find the documentation for the plugin in the Cake Book](http://book.cakephp.org/3.0/en/elasticsearch.html).

## Installing ElasticSearch via composer

You can install ElasticSearch into your project using
[composer](http://getcomposer.org). For existing applications you can add the
following to your `composer.json` file:

    "require": {
        "cakephp/elastic-search": "dev-master"
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

Before you can do any work with elasticsearch models, you'll need to define
a connection:

```php
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
```

You can enable request logging by setting the `log` config option to true. By
default, `Elastica\Log` will be used, which logs via `error_log`. You can also
define an `elasticsearch` log profile in `Cake\Log\Log` to customize where
elasticsearch query logs will go. Query logging is done at a 'debug' level.

## Getting a Type object

Type objects are the equivalent of `ORM\Table` instances in elastic search. You can
use the `TypeRegistry` factory to get instances, much like `TableRegistry`:

```php
use Cake\ElasticSearch\TypeRegistry;

$comments = TypeRegistry::get('Comments');
```

## Running tests

Assuming you have PHPUnit installed system wide using one of the methods stated
[here](http://phpunit.de/manual/current/en/installation.html), you can run the
tests for CakePHP by doing the following:

1. Copy `phpunit.xml.dist` to `phpunit.xml`
2. Run `phpunit`
