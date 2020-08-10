# Управление ElasticSearch + MODX + mSearch + SEOFilter
Умеет формировать запросы в эластик

# Установка
Подключим для MODX . Для этого нужно создать файл composer.json (или выполнить composer init) и добавить в него соответстствующие разделы:

```
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/webnitros/Elastic"
    }
  ],
  "require": {
    "webnitros/Elastic": "1.*@beta",
  }
```

```
composer install
```

```php
<?php
namespace App\Elastic;

define('MODX_API_MODE', true);
require 'index.php';

require_once dirname(__FILE__) . '/vendor/autoload.php';

$Search = new Search($modx);
```


## Запрос для получения списка Агрегаций
для работы нужен установленный $modx

```php

$indexName = 'products';
$Search = new Search($modx);
if ($Search->initialize('products')) {
    $Seo = $Search->seoAgregations();
    $data = $Seo->get(4, 2);
    echo '<pre>';
    print_r($data);
    die;
}
```

## Поиск

```php

$request = [
    'vendor_name.keyword' => 'Samsung'
];

$Search = new Search();
if ($Search->initialize('products')) {
    $response = $Search->setFields([
        '' => ''
    ]);
    try {
        $Search->criteria->reset();
        $params = $Search->getDefaultParams();
        $from = 0;
        $limit = 10;

        $params['body'] = [
            'from' => $from,
            'size' => $limit,
            #'sort' => 'id',
            '_source' => ['*'],
        ];


        $Search->criteria->setRequest($request);

        $body = $Search->criteria->process();
        $params['body'] = array_merge($params['body'], $body);


        $Search->aggregations->isAddMinCount();
        $Search->aggregations->isDefaultFilterCategoryEnable();
        $aggregations = $Search->aggregations->get();
        $params['body']['aggs'] = $aggregations;

        $response = $Search->search($params);


        $results = [];
        foreach ($response['hits']['hits'] as $hit) {
            $id = $hit['_id'];
            $data = array_merge(['id' => $id], $hit['_source']);
            $results[] = $data;
        }

        $res = [
            'total' => !empty($response['hits']['total']['value']) ? $response['hits']['total']['value'] : 0,
            'page' => 1,
            'pages' => 14,
            'pagination' => '',
            'results' => $results,
            'suggestions' => $Search->suggestions->get($response['aggregations']),
        ];

    } catch (Exception $e) {
        $res = $modx->fromJSON($e->getMessage());
    }
}
```

## Подключение composer

в composer
```
"autoload": {
        "psr-4": {
            "App\\": "Elastic/src/"
        }
    }
```

```
composer dump-autoload -o
```