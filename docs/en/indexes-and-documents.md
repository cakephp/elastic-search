# Indexes and Documents

The ElasticSearch plugin uses repository-style `Index` classes and ORM-like `Document` classes.

## Creating an Index Class

Create an index class in your application's `Model/Index` namespace:

```php
namespace App\Model\Index;

use Cake\ElasticSearch\Index;

class ArticlesIndex extends Index
{
}
```

`Index` objects are similar to `ORM\Table` instances. Each index class maps to an Elasticsearch index, and by default the plugin infers the index name from the class name.

If you need to define the Elasticsearch index name explicitly, override `getName()`:

```php
namespace App\Model\Index;

use Cake\ElasticSearch\Index;

class CommentsIndex extends Index
{
    public function getName(): string
    {
        return 'comments';
    }
}
```

## Creating a Document Class

Document classes belong in `Model/Document`:

```php
namespace App\Model\Document;

use Cake\ElasticSearch\Document;

class Article extends Document
{
}
```

Aside from constructor logic needed to hydrate Elasticsearch data, `Document` behaves like a CakePHP entity. The entity documentation for CakePHP is therefore a good reference for getters, setters, hidden fields, virtual fields, and mass-assignment rules.

## Fetching Index Instances

Use `IndexLocatorAwareTrait` when you want convenient access in controllers, commands, and services:

```php
use Cake\ElasticSearch\Datasource\IndexLocatorAwareTrait;

class MyController extends Controller
{
    use IndexLocatorAwareTrait;

    public function index()
    {
        $articles = $this->fetchIndex('Articles');
    }
}
```

You can also work with `IndexLocator` directly:

```php
use Cake\ElasticSearch\Datasource\IndexLocator;

$locator = new IndexLocator();
$articles = $locator->get('Articles');
```

Clear the locator during tests when you need fresh instances or mocked dependencies:

```php
$locator->clear();
```

## Configuring the Connection Per Index

All indexes use the `elastic` connection by default. Override `defaultConnectionName()` if an index should use a different datasource:

```php
namespace App\Model\Index;

use Cake\ElasticSearch\Index;

class ArticlesIndex extends Index
{
    public static function defaultConnectionName(): string
    {
        return 'replica_db';
    }
}
```

::: warning Static method required
`defaultConnectionName()` must be declared `static`.
:::
