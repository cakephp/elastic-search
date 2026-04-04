# Installation and Configuration

Install the plugin with Composer from your application's root directory:

```bash
composer require cakephp/elastic-search:^5.0
```

Load the plugin in your application bootstrap:

```php
use Cake\ElasticSearch\Plugin as ElasticSearchPlugin;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin(ElasticSearchPlugin::class);

        // To disable the automatic model provider and FormHelper wiring:
        // $this->addPlugin(ElasticSearchPlugin::class, ['bootstrap' => false]);
    }
}
```

## Datasource Configuration

Configure an `elastic` datasource in `config/app.php`:

```php
// in config/app.php
'Datasources' => [
    // other datasources
    'elastic' => [
        'className' => 'Cake\ElasticSearch\Datasource\Connection',
        'driver' => 'Cake\ElasticSearch\Datasource\Connection',
        'hosts' => ['127.0.0.1:9200'],
        'index' => 'my_apps_index',
    ],
]
```

The legacy `host` and `port` keys are still supported:

```php
'elastic' => [
    'className' => 'Cake\ElasticSearch\Datasource\Connection',
    'driver' => 'Cake\ElasticSearch\Datasource\Connection',
    'host' => '127.0.0.1',
    'port' => 9200,
    'index' => 'my_apps_index',
]
```

For HTTPS endpoints, use full URLs in the `hosts` array:

```php
'hosts' => ['https://127.0.0.1:443']
```

For multi-node clusters, provide multiple hosts:

```php
'hosts' => [
    '127.0.0.1:9200',
    '127.0.0.1:9201',
    '127.0.0.1:9202',
]
```

You can also configure the connection with a URL, which is useful when values come from environment variables:

```php
'Datasources' => [
    'elastic' => [
        'url' => env('ELASTIC_URL', null),
    ],
]
```

```text
ELASTIC_URL="Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?driver=Cake\ElasticSearch\Datasource\Connection"
```

::: tip Recommended format
The `hosts` array is the recommended format for new applications because it aligns with Elastica `9.x` and works better with multiple hosts.
:::

## Query Logging

Enable request logging by setting `log` to `true` on the datasource configuration. By default the `debug` log profile is used. You can define an `elasticsearch` log profile in `Cake\Log\Log` if you want query logs written elsewhere.
