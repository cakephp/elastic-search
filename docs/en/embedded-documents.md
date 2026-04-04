# Embedded Documents

Embedded documents let you attach custom document classes to nested properties in a parent document.

For example, if articles contain embedded users and comments, you can define those relationships in the index:

```php
namespace App\Model\Index;

use Cake\ElasticSearch\Index;

class ArticlesIndex extends Index
{
    public function initialize(): void
    {
        $this->embedOne('User');
        $this->embedMany('Comments', [
            'entityClass' => 'MyComment',
        ]);
    }
}
```

In this example:

- `embedOne('User')` maps the `user` property to `App\Model\Document\User`.
- `embedMany('Comments')` maps the `comments` property to an array of document objects.
- `entityClass` lets you use a class name that does not match the property name.

After configuring embedded documents, `find()` and `get()` hydrate the nested properties into the configured document classes:

```php
$article = $this->Articles->get($id);

// Instance of App\Model\Document\User
$article->user;

// Array of App\Model\Document\Comment instances
$article->comments;
```
