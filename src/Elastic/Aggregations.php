<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 17:56
 */
namespace App\Elastic;

use App\Elastic\Criteria;

class Aggregations
{
    /* @var Search $es */
    public $es;

    /* @var null|array $aggregations */
    protected $aggregations = null;

    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    /* @var boolean $addMinCount - устанавливаем для агрегаций чтобы возвращались все значения в то числе и нулевые */
    protected $addMinCount = false;

    /**
     * Возвращать записи с нулевыми значениями
     * Если этого не делать то будут оставаться пустые фильтры
     */
    public function isAddMinCount()
    {
        $this->addMinCount = true;
    }

    /* @var boolean $isDefaultFilterCategory - true вернуться все значения для выбранной категории с исключением любых наложенных фильтров */
    protected $isDefaultFilterCategory = true;

    /**
     * Исключение належения фильтов
     */
    public function isDefaultFilterCategoryEnable()
    {
        $this->isDefaultFilterCategory = true;
    }

    /**
     * Исключение належения фильтов
     */
    public function isDefaultFilterCategoryDisable()
    {
        $this->isDefaultFilterCategory = false;
    }


    /**
     * Вернет параметры для агрегации
     * @return array
     */
    public function get()
    {
        $fields = $this->es->getFields();
        foreach ($fields as $field => $meta) {
            $filter = $meta['filter'];
            // ПРоверка разрешения подсчитывать результаты
            $is_aggs = $meta['aggs'];
            if ($is_aggs) {
                if ($agg = $this->aggs($field, $filter)) {
                    $this->aggregations = !$this->aggregations ? $agg : array_merge($this->aggregations, $agg);
                }
            }
        }
        return $this->aggregations;
    }

    /**
     * @param $field
     * @param $type
     * @return array
     */
    private function aggs($field, $type)
    {
        $params = array();
        switch ($type) {
            case 'facets':
                $params[$field] = $this->addCriteriaFacets($field);
                break;
            case 'terms':
                $params[$field] = $this->addCriteriaTerms($field);
                break;
            case 'term':
                $params[$field] = $this->addCriteriaTerms($field);
                break;
            case 'range':
                $params[$field] = $this->addCriteriaRange($field);
                break;
            default:
                break;
        }
        return $params;
    }

    private function addCriteriaFacets($field)
    {
        $criteria = $this->es->criteria->body['post_filter']['bool']['filter'];
        $meta = $this->es->getField($field);
        if (!array_key_exists('field_rating', $meta)) {
            return false;
        }
        $field_rating = $meta['field_rating'];

        //field
        $response = array(
            'range' => array(
                'field' => $field_rating,
                'keyed' => true,
                'ranges' => array(
                    ["key" => "all", "from" => 0],
                    ["from" => 900, "to" => 2000],
                    ["from" => 5000, "to" => 10000],
                    ["key" => "max", "from" => 10000],
                )
            ),
            'aggs' => array(
                'prices_stats' => array(
                    'stats' => array(
                        'field' => $field_rating,
                        'missing' => 0
                    )
                )
            )
        );
        return $response;
    }

    private function addCriteriaRange($field)
    {
        $filter = array();
        $selectedValues = array();
        if ($this->isDefaultFilterCategory) {
            $criteria = $this->es->criteria->body['post_filter']['bool']['filter'];
            if (!empty($criteria) and is_array($criteria)) {
                foreach ($criteria as $f => $values) {
                    $terms = $values['range'];
                    if (!empty($terms) and array_key_exists($field, $terms)) {
                        $selectedValues = $terms[$field];
                        continue;
                    }
                    $filter[] = $values;
                }
            }
        }
        $order = array(
            '_count' => 'desc'
        );
        $response = array(
            'filter' => array(
                'bool' => array(
                    'must' => $filter
                )
            ),
            'aggs' => array(
                $field . '_min' => array(
                    'min' => array(
                        'field' => $field,
                    )
                ),
                $field . '_max' => array(
                    'max' => array(
                        'field' => $field,
                    )
                ),

            )
        );
        return $response;
    }

    private function addCriteriaTerms($field)
    {
        $field .= '.keyword';

        $filter = array();
        $selectedValues = array();
        if ($this->isDefaultFilterCategory) {
            $criteria = $this->es->criteria->body['post_filter']['bool']['filter'];
            if (!empty($criteria) and is_array($criteria)) {
                foreach ($criteria as $f => $values) {
                    $terms = $values['terms'];
                    if (!empty($terms) and array_key_exists($field, $terms)) {
                        $selectedValues = $terms[$field];
                        continue;
                    }
                    $filter[] = $values;
                }
            }
        }


        $order = array(
            '_count' => 'desc'
        );

        $response = array(
            'filter' => array(
                'bool' => array(
                    'must' => $filter
                )
            ),
            'aggs' => array(
                $field => array(
                    'terms' => array(
                        'field' => $field,
                        'size' => 100,
                        'exclude' => $selectedValues,
                        #'missing' => "",
                        'order' => $order,
                    )
                ),
                $field . '_selected' => array(
                    'terms' => array(
                        'field' => $field,
                        'size' => 100,
                        'include' => $selectedValues,
                        #'missing' => "",
                        'order' => array(
                            '_count' => 'desc'
                        ),
                    ),
                )
            )
        );
        if ($this->addMinCount) {
            $response['aggs'][$field]['terms']['min_doc_count'] = 0;
            $response['aggs'][$field . '_selected']['terms']['min_doc_count'] = 0;
        }


        return $response;
    }

    private function getFacetPrices($parent, $price)
    {
        $price = (int)$price;
        $priceRanges = array(
            ['from' => 0, "to" => 900],
            ["from" => 900, "to" => 5000],
            ["from" => 5000, "to" => 10000],
            ["from" => 10000],
        );
        $response = false;
        foreach ($priceRanges as $priceRange) {
            $from = !empty($priceRange['from']) ? $priceRange['from'] : 0;
            $to = !empty($priceRange['to']) ? $priceRange['to'] : 0;
            if ($price >= $from and $price <= $to) {
                $key = $from . '-' . $to;
                return (string)$key;
            }
        }
        return '0';
    }


}