<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 17:56
 */

namespace App\Elastic;

use App\Elastic\Aggregations;
use App\Elastic\Client;
use App\Elastic\Criteria;
use App\Elastic\SeoAgregations;
use Elasticsearch\ClientBuilder;
use Exception;

class Search
{
    /* @var string|null $index_name */
    private $index_name;
    /* @var string|null $index_type */
    private $index_type;

    /* @var array|null $fields */
    private $fields = null;

    /* @var Aggregations|null $aggregations */
    public $aggregations;

    /* @var Criteria|null $criteria */
    public $criteria;
    /* @var Suggestions $suggestions */
    public $suggestions;

    /* @var Client|null $client */
    public $client = null;

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * @param null $index_name
     * @param null $index_type
     * @return Search
     */
    public function initialize($index_name = null, $index_type = null): Search
    {
        $this->client = new Client();
        $this->aggregations = new Aggregations($this);
        $this->criteria = new Criteria($this);
        $this->suggestions = new Suggestions($this);
        $this->setIndex($index_name, $index_type);
        return $this;
    }

    /**
     * @param array $fields
     * @return bool
     */
    public function setFields($fields = [])
    {
        if (is_array($fields)) {
            $this->fields = $fields;
            return true;
        }
        return false;
    }


    /**
     * Вернет карту полей с типом поля и фильтром
     * @return array|null
     */
    public function getFields()
    {
        $fields = null;
        if (is_array($this->fields)) {
            $fields = $this->fields;
        }
        return $fields;
    }

    /**
     * @param false $isCount - подсчет количества
     * @return array
     */
    public function getDefaultParams($isCount = false)
    {
        $params = [];
        $params['index'] = $this->getIndexName();
        if ($type = $this->getIndexType()) {
            $params['type'] = $type;
        }
        $params['body'] = [
            'query' => array(
                'bool' => array()
            ),
        ];
        if (!$isCount) {
            $params['body']['from'] = 0;
            $params['body']['size'] = 0;
        }
        return $params;
    }

    /**
     * @return string|null
     */
    public function getIndexName()
    {
        return $this->index_name;
    }

    /**
     * @return string|null
     */
    public function getIndexType()
    {
        return $this->index_type;
    }

    /**
     * @param null $index_name
     * @param null $index_type
     */
    public function setIndex($index_name = null, $index_type = null)
    {
        $this->index_name = $index_name;
        $this->index_type = $index_type;
    }

    /**
     * Вернет карту полей с типом поля и фильтром
     * @return array|null
     */
    public function getAliases()
    {
        $fields = $this->getFields();
        $aliases = [];
        foreach ($fields as $field => $meta) {
            if (array_key_exists('alias', $meta)) {
                $aliases[$meta['alias']] = $field;
            }
        }
        return $aliases;
    }


    /**
     * Вернет карту полей с типом поля и фильтром
     * @return array|null
     */
    public function getFilterFields()
    {
        $fields = $this->getFields();
        $filterFields = array();
        foreach ($fields as $field => $meta) {
            $filterFields[$field] = $meta['filter'];
        }
        return $filterFields;
    }


    /**
     * Вернет мета данные для поля
     * @param $name
     * @return array|null
     */
    public function getField($name)
    {
        $fields = $this->getFields();
        if (array_key_exists($name, $fields)) {
            return $fields[$name];
        }
        return null;
    }

    public $errors = array();

    /**
     * @return bool
     */
    public function exists()
    {
        $response = $this->client->process('exists', ['index' => $this->index_name]);
        if (!is_bool($response)) {
            return false;
        }
        return $response;
    }


    /**
     * Вернет критерии запроса
     * @return array
     */
    public function getCriteria()
    {
        return $this->criteria->getCriteria();
    }

    /**
     * @param array $params
     * @return array|callable
     */
    public function search($params = [])
    {
        return $this->client->process('search', $params);
    }

    /**
     * @return \App\Elastic\SeoAgregations
     */
    public function seoAgregations()
    {
        return new SeoAgregations($this);
    }


    /**
     * @return bool
     */
    public function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }
}