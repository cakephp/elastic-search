# ElasticSearch Datasource for CakePHP
[![License](https://poser.pugx.org/cakephp/elastic-search/license.svg)](https://packagist.org/packages/cakephp/elastic-search)

This is a pre-alpha version of an alternative ORM for CakePHP 3.0 using [Elastic Search](http://www.elasticsearch.org/)
as its backend. It is currently under development and is only being used to test the
interfaces exposed in CakePHP 3.0.

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

``php
// in config/bootstrap.php
Plugin::load('ElasticSearch');

// If you want the plugin to automatically configure the Elastic model provider
// and FormHelper do the following:
Plugin::load('ElasticSearch', ['bootstrap' => true]);
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
