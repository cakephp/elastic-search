# Searching and Saving

Once you have an index class, you can build queries, validate data, and persist documents through it.

## Searching Indexed Documents

Use `find()` to create a query:

```php
$query = $this->Articles->find()
    ->where([
        'title' => 'special',
        'or' => [
            'tags in' => ['cake', 'php'],
            'tags not in' => ['c#', 'java'],
        ],
    ]);

foreach ($query as $article) {
    echo $article->title;
}
```

Use the query builder callback for more expressive conditions:

```php
$query->where(function ($builder) {
    return $builder->and(
        $builder->gt('views', 99),
        $builder->term('author.name', 'sally')
    );
});
```

Refer to the `QueryBuilder` API for the full set of supported methods.

## Validating and Applying Rules

Validation and application rules work the same way they do in the relational ORM. Validation runs during marshalling, and rule checks run before a document is persisted.

## Saving New Documents

Create and save new documents with `newEntity()` and `save()`:

```php
$article = $this->Articles->newEntity($data);
if ($this->Articles->save($article)) {
    // Document was indexed
}
```

To marshal embedded documents, pass the `associated` option:

```php
$article = $this->Articles->newEntity($data, [
    'associated' => ['Comments'],
]);
```

Saving a document triggers these events:

- `Model.beforeSave` fires before the document is saved. Stop this event to prevent the save.
- `Model.buildRules` fires when the rules checker is built for the first time.
- `Model.afterSave` fires after the document is saved.

::: info Embedded documents
Embedded documents do not emit their own save events because the parent document and its embedded records are persisted as one operation.
:::

## Updating Existing Documents

Patch or modify documents and save them again:

```php
$query = $this->Articles->find()->where(['user.name' => 'jill']);
foreach ($query as $doc) {
    $doc->set($newProperties);
    $this->Articles->save($doc);
}
```

Pass `refresh => true` if you need Elasticsearch to refresh the index after a write:

```php
$this->Articles->save($article, ['refresh' => true]);
```

## Saving Multiple Documents

Use `saveMany()` to bulk save documents:

```php
$result = $this->Articles->saveMany($documents);
```

`$documents` must be an array of document objects. `saveMany()` returns `true` on success or `false` on failure, and it accepts the same options as `save()`.

## Deleting Documents

Delete a single document after retrieving it:

```php
$doc = $this->Articles->get($id);
$this->Articles->delete($doc);
```

Delete matching documents in bulk with `deleteAll()`:

```php
$this->Articles->deleteAll(['user.name' => 'bob']);
```
