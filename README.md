# Elasticsearch Datasource for CakePHP

![Build Status](https://github.com/cakephp/elastic-search/actions/workflows/ci.yml/badge.svg?branch=5.x)
[![Latest Stable Version](https://img.shields.io/github/v/release/cakephp/elastic-search?sort=semver&style=flat-square)](https://packagist.org/packages/cakephp/elastic-search)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/elastic-search?style=flat-square)](https://packagist.org/packages/cakephp/elastic-search/stats)
[![codecov](https://codecov.io/gh/cakephp/elastic-search/branch/5.x/graph/badge.svg?token=G3Tcg116OX)](https://app.codecov.io/gh/cakephp/elastic-search/tree/5.x)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Use [Elastic Search](https://www.elastic.co/) as an alternative ORM backend in CakePHP 5.2+.

You can [find the documentation for the plugin in the Cake Book](https://book.cakephp.org/elasticsearch).

## Installing Elasticsearch via composer

You can install Elasticsearch into your project using
[composer](https://getcomposer.org). For existing applications you can run:

```bash
composer require cakephp/elastic-search:^5.0
```

### Versions Table

| Cake\ElasticSearch                                                 | CakePHP   | ElasticSearch | Elastica  |
| ---                                                                | ---       | ---           | ---       |
| [1.x](https://github.com/cakephp/elastic-search/tree/1.0)          | 3.0 - 3.5 | 2.x - 5.x     | 5.x - 6.x |
| [2.x](https://github.com/cakephp/elastic-search/tree/2.x)          | 3.6+      | 6.x           | 6.x       |
| [>3, <3.4.0](https://github.com/cakephp/elastic-search/tree/3.3.0) | 4.0+      | 6.x           | 6.x       |
| [>=3.4.0](https://github.com/cakephp/elastic-search/tree/3.x)      | 4.0+      | 7.x           | 7.x       |
| [4.x](https://github.com/cakephp/elastic-search/tree/4.x)          | 5.0+      | 7.x           | 7.x       |
| [5.x](https://github.com/cakephp/elastic-search/tree/5.x)          | 5.2+      | 9.x           | 9.x       |

You are seeing the 5.x version.

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
        'hosts' => ['127.0.0.1:9200']
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
use the `IndexLocatorAwareTrait` to get instances in your classes:

```php
use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;

class MyClass
{
    use IndexLocatorAwareTrait;

    public function someMethod()
    {
        $comments = $this->fetchIndex('Comments');
    }
}
```

Alternatively, you can use the `IndexLocator` directly:

```php
use Cake\ElasticSearch\Datasource\IndexLocator;

$locator = new IndexLocator();
$comments = $locator->get('Comments');
```

> **Note for upgrading users**: The `IndexRegistry` class has been deprecated since version 3.4.3. If you're upgrading from an older version, replace `IndexRegistry::get('Comments')` with the `IndexLocatorAwareTrait` approach shown above or use `IndexLocator` directly.

Each `Index` object needs a correspondent Elasticsearch _index_, just like most of `ORM\Table` needs a database _table_.

In the above example, if you have defined a class as `CommentsIndex` and the `IndexLocator` can find it, the `$comments` will receive an initialized object with inner configurations of connection and index. But if you don't have that class, a default one will be initialized and the index name on Elasticsearch mapped to the class.

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
}
```

## Running tests

We recommend using the included `docker-compose.yml` for doing local
development. The `Dockerfile` contains the development environment, and an
Elasticsearch container will be downloaded and started on port 9200.

```bash
# Start elasticsearch
docker-compose up -d
```

Once inside the container you can install dependencies and run tests.

```bash
composer install
composer test
```

**Warning**: Please, be very carefully when running tests as the Fixture will
create and drop Elasticsearch indexes for its internal structure. Don't run tests
in production or development machines where you have important data into your
Elasticsearch instance.

Assuming you have PHPUnit installed system wide using one of the methods stated
[here](https://phpunit.de/manual/current/en/installation.html), you can run the
tests for CakePHP by doing the following:

1. Copy `phpunit.xml.dist` to `phpunit.xml`
2. Run `phpunit`
