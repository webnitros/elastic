<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 09.08.2020
 * Time: 11:35
 */

namespace App\Elastic;

use modX;
use pdoTools;
use sfRule;
use PDO;
use msCategory;

class LinkAssembly
{
    /* @var string $alias */
    private $alias;

    /* @var array $arrays */
    private $arrays = [];
    private $links = array();
    private $params = array();

    /* @var array $aggs */
    private $aggs = array();

    /* @var Search $es */
    public $es;

    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    /**
     * @param array $aliases
     * @param array $aggregations
     */
    public function get($aliases = [], $aggregations = [])
    {
        $response = $this->buckets($aggregations, $aliases, 0);
        return [
            'links_count' => count($this->links),
            'urls' => $this->getUrls($aliases),
            'params' => $this->params,
            'links' => $this->links,
        ];
    }

    public function bucket($buckets)
    {
        $arrays = [];
        foreach ($buckets as $bucket) {
            $arrays[$bucket['key']] = $bucket['doc_count'];
        }
        return $arrays;
    }

    /**
     * @return pdoTools
     */
    public function pdoTools()
    {
        /* @var pdoTools $pdoTools */
        $pdoTools = $this->es->modx->getService('pdoTools');
        return $pdoTools;
    }

    /**
     * @param $key
     * @param array $arrays
     * @return array
     */
    private function newKeyWord($key, $arrays = [])
    {
        $property = [];
        foreach ($arrays as $k => $v) {
            if (strripos($k, 'value') !== false) {
                $new_key = str_ireplace('value', $key, $k);
                $property[$new_key] = $v;
            }
        }
        return $property;
    }

    /**
     * @param array $aliases
     * @return array
     */
    private function getUrls($aliases = [])
    {
        $pdoTools = $this->pdoTools();

        $chunk_url = $chunk_link = null;
        /* @var sfRule $Rule */
        if ($Rule = $this->es->modx->getObject('sfRule', 4)) {
            $chunk_url = $Rule->get('url');
            $chunk_link = $Rule->get('link_tpl');
        }

        $category_words = $category_uri = null;
        $page = $Rule->get('page');

        if ($this->es->modx->resource instanceof msCategory) {
            $page = $this->es->modx->resource->get('id');
        }


        /* @var msCategory $Category */
        if ($Category = $this->es->modx->getObject('msCategory', $page)) {
            $category_uri = $Category->get('uri');
            $category = Morpher::create()->request($Category->get('pagetitle'));
            $category_words = $this->newKeyWord('category', $category);
            $category_words['category'] = $Category->get('pagetitle');
        }



        $properties = [];
        $urlsAliases = [];
        $q = $this->es->modx->newQuery('sfDictionary');
        $q->select($this->es->modx->getSelectColumns('sfDictionary', 'sfDictionary'));
        $q->select('Field.alias as field,sfDictionary.input,sfDictionary.value,sfDictionary.alias as alias');
        $q->where(array(
            'Field.alias:IN' => $aliases,
        ));
        $q->innerJoin('sfField', 'Field', 'Field.id = sfDictionary.field_id');
        if ($q->prepare() && $q->stmt->execute()) {
            while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
                $properties[$row['field']][$row['value']] = $row;
                $urlsAliases[$row['field']][$row['value']] = $row['alias'];
            }
        }


        $urls = [];
        foreach ($this->links as $k => $link) {

            $doc_count = $link['doc_count'];
            $params = $link['params'];
            $collected = true;
            $pls = $category_words;

            $url = [];
            foreach ($params as $field => $value) {
                if (!empty($urlsAliases[$field][$value])) {

                    $tmp = $this->newKeyWord($field, $properties[$field][$value]);
                    $pls = array_merge($pls, $tmp);

                    #$property[$field] = $tmpValues;
                    #$url[$field] = $urlsAliases[$field][$value];
                    $url[$field] = $urlsAliases[$field][$value];

                } else {
                    // Если какой то параметр не найден то ссылка не пригодная
                    $collected = false;
                }
            }


            $html_url = $pdoTools->getChunk('@INLINE ' . $chunk_url, $url);
            $html_link = $pdoTools->getChunk('@INLINE ' . $chunk_link, $pls);
            if ($collected) {
                $url_str = implode('/', $url);
                $urls[] = [
                    'url' => $category_uri.$html_url,
                    'count' => $doc_count,
                    'name' => $html_link,
                ];

                /*
                $urls[] = [
                   'url' => $url_str,
                   'count' => $doc_count,
                   'name' => implode(' ', $params),
               ];
                */
            }
        }
        if (!empty($urls)) {
            for ($i = 0; $i < count($urls); $i++) {
                $sortkey[$i] = $urls[$i]['count'];
            }
            asort($sortkey);
            foreach ($sortkey as $key => $key) {
                $sorted[] = $urls[$key];
            }
            $urls = array_reverse($sorted);
        }
        return $urls;
    }


    /**
     * @param array $buckets
     * @param array $aliases
     * @param int $i
     * @return bool
     */
    private function buckets($buckets = [], $aliases = [], $i = 0)
    {
        if (!array_key_exists($i, $aliases)) {
            /* $hash = $this->getHash($this->params);
             $this->links[$hash] = [
                 'doc_count' => $buckets['doc_count'],
                 'params' => $this->params,
             ];*/
            $this->links[] = [
                'doc_count' => $buckets['doc_count'],
                'params' => $this->params,
            ];
            return false;
        }

        $alias = $aliases[$i];
        if ($i == 0) {
            $result = $buckets[$alias][$alias]['buckets'];
        } else {
            $result = $buckets[$alias]['buckets'];
        }

        $i++;
        foreach ($result as $item) {
            $key = $item['key'];
            $this->params[$alias] = $key;
            $this->buckets($item, $aliases, $i);
        }
        return true;
    }


    /**
     * @param array $params
     *
     * @return string
     */
    private function getHash(array $params)
    {
        $keys = array_keys($params);
        $keys = $this->natsort($keys);

        $values = array_values($params);
        foreach ($values as $k => $v) {
            if (is_array($v)) {
                unset($values[$k]);
            }
        }
        $values = $this->natsort($values);
        $str = implode($keys) . implode($values);

        return md5(strtolower($str));
    }


    /**
     * @param array $array
     *
     * @return array
     */
    private function natsort(array $array)
    {
        $ints = $strings = array();
        foreach ($array as $v) {
            if (is_numeric($v) || is_bool($v)) {
                $ints[] = (int)$v;
            } elseif (is_array($v)) {
                // Exclude arrays
            } else {
                $strings[] = (string)$v;
            }
        }
        sort($ints);
        sort($strings);

        $res = array();
        foreach ($ints as $v) {
            $res[] = (string)$v;
        }
        foreach ($strings as $v) {
            $res[] = $v;
        }

        return $res;
    }


}