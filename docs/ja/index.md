# ElasticSearch

ElasticSearch プラグインは、[Elasticsearch](https://www.elastic.co/products/elasticsearch) の上に ORM のような抽象化を提供します。テストの作成、ドキュメントのインデックス作成、インデックスの検索を CakePHP から扱いやすくします。

## インストール

アプリケーションのルートディレクトリーで Composer を使ってインストールします。

```bash
composer require cakephp/elastic-search:^5.0
```

`src/Application.php` でプラグインを読み込みます。

```php
use Cake\ElasticSearch\Plugin as ElasticSearchPlugin;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin(ElasticSearchPlugin::class);
    }
}
```

`config/app.php` に `elastic` データソースを追加します。

```php
'Datasources' => [
    'elastic' => [
        'className' => 'Cake\ElasticSearch\Datasource\Connection',
        'driver' => 'Cake\ElasticSearch\Datasource\Connection',
        'hosts' => ['127.0.0.1:9200'],
        'index' => 'my_apps_index',
    ],
]
```

以前の `host` と `port` を使う形式も引き続き利用できます。

## 概要

ElasticSearch プラグインは Elasticsearch のインデックスを CakePHP ORM に近い感覚で扱えるようにします。まず `Index` クラスを作成します。

```php
namespace App\Model\Index;

use Cake\ElasticSearch\Index;

class ArticlesIndex extends Index
{
}
```

次に `Document` クラスを作成します。

```php
namespace App\Model\Document;

use Cake\ElasticSearch\Document;

class Article extends Document
{
}
```

## インデックス付きドキュメントの検索

`find()` と `where()` で検索クエリーを構築できます。

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

`QueryBuilder` を使えばより細かい条件も組み立てられます。

```php
$query->where(function ($builder) {
    return $builder->and(
        $builder->gt('views', 99),
        $builder->term('author.name', 'sally')
    );
});
```

## 新しいドキュメントの保存

```php
$article = $this->Articles->newEntity($data);
if ($this->Articles->save($article)) {
    // Document はインデックスされました
}
```

埋め込みドキュメントをマーシャリングする場合は `associated` を指定します。

```php
$article = $this->Articles->newEntity($data, [
    'associated' => ['Comments'],
]);
```

保存時には以下のイベントが発生します。

- `Model.beforeSave`
- `Model.buildRules`
- `Model.afterSave`

## 既存ドキュメントの更新と削除

```php
$query = $this->Articles->find()->where(['user.name' => 'jill']);
foreach ($query as $doc) {
    $doc->set($newProperties);
    $this->Articles->save($doc);
}
```

```php
$doc = $this->Articles->get($id);
$this->Articles->delete($doc);
```

条件に一致するドキュメントをまとめて削除することもできます。

```php
$this->Articles->deleteAll(['user.name' => 'bob']);
```

## 埋め込みドキュメント

`embedOne()` と `embedMany()` を使うと、ネストしたプロパティーに独自のドキュメントクラスを割り当てられます。

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

`find()` や `get()` の結果は、設定した埋め込みドキュメントクラスに変換されます。

## インデックスインスタンスの取得

`IndexLocatorAwareTrait` を使うとインデックスを簡単に取得できます。

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

または `IndexLocator` を直接使えます。

```php
use Cake\ElasticSearch\Datasource\IndexLocator;

$locator = new IndexLocator();
$articles = $locator->get('Articles');
$locator->clear();
```

## テストフィクスチャー

`MappingGenerator` を使ってテスト用のマッピングを読み込めます。

```php
use Cake\ElasticSearch\TestSuite\Fixture\MappingGenerator;

$generator = new MappingGenerator('tests/mappings.php', 'test_elastic');
$generator->reload();
```

フィクスチャーは `Cake\ElasticSearch\TestSuite\TestFixture` を継承して作成します。

```php
namespace App\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ArticlesFixture extends TestFixture
{
    public string $table = 'articles';

    public array $records = [
        [
            'user' => ['username' => 'billy'],
            'title' => 'First Post',
            'body' => 'Some content',
        ],
    ];
}
```
