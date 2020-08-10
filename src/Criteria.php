<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 17:56
 */

namespace App\Elastic;

class Criteria
{
    /* @var Search $es */
    public $es;
    protected $criteria = array();
    protected $selected = array();
    public $body = array();

    /* @var array $_request */
    protected $_request = array();
    protected $keyPostFilter = 'post_filter';

    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    /**
     * Сброс переменных
     */
    public function reset()
    {
        $this->body = array();
        $this->_request = array();
    }

    public function getBody()
    {
        $body = $this->body;
        if ($this->keyPostFilter == 'post_filter') {
            $body['post_filter']['bool']['filter'] = !empty($this->body['post_filter']['bool']['filter']) ? array_values($this->body['post_filter']['bool']['filter']) : array();
        }
        $body['query']['bool']['filter'] = !empty($this->body['query']['bool']['filter']) ? array_values($this->body['query']['bool']['filter']) : array();
        return $body;
    }

    /**
     * @param $values
     * @return array
     */
    public function clearValues($values)
    {
        $values = array_filter($values);
        $values = array_unique($values);
        return array_values($values);
    }

    protected $parent_id = null;

    public function setParent($parent_id)
    {
        $this->parent_id = $parent_id;
    }

    /**
     * @param false $build
     * @return array
     */
    public function getDefaultRequest($build = false)
    {
        #if (!$build) {
        // Не добавляем при сборе значений для фильтров
        $request['availability'] = true;
        #}
        return $request;
    }

    /**
     * @return array
     */
    public function getPostCriteria()
    {
        $criteria = $this->getBody();
        $filters = $criteria[$this->keyPostFilter]['bool']['filter'];

        $arrays = array();
        foreach ($filters as $filter) {
            $arrays[] = $filter;
        }

        return $arrays;
    }


    /**
     * Записывам параметры в $_REQUEST и $_GET
     * @param array $params
     */
    public function setRequest($params = array())
    {
        $this->body = array();
        $this->_request = array();
        foreach ($params as $field => $value) {
            $this->_request[$field] = $value;
        }
    }

    /**
     * @param false $build
     * @return array
     */
    public function process($build = false)
    {
        $fieldsFilters = $this->es->getFields();
        $request = $this->getDefaultRequest($build);
        foreach ($this->_request as $filter => $requested) {
            if (array_key_exists($filter, $fieldsFilters)) {
                $request[$filter] = $requested;
            }
        }

        foreach ($request as $filter => $requested) {
            $typeFilter = null;
            if (!array_key_exists($filter, $fieldsFilters)) {
                continue;
            }
            $meta = $fieldsFilters[$filter];
            $typeFilter = $meta['filter'];
            $typePhp = $meta['type'];
            if (!$typeFilter) {
                continue;
            }
            switch ($typeFilter) {
                case 'term':
                    $values = null;
                    switch ($typePhp) {
                        case 'boolean':
                            $values = (boolean)$requested;
                            break;
                        default:
                            $values = $requested;
                            break;
                    }

                    if ($values) {
                        $this->addQueryBoolFilter($filter, array(
                            'term' => array(
                                $filter => $values
                            )
                        ));
                    }
                    break;
                case 'range':
                    $values = explode(',', $requested);
                    $this->addPostFilterBoolFilter($filter, array(
                        'range' => array(
                            $filter => array(
                                'gte' => $values[0],
                                'lte' => $values[1]
                            )
                        )
                    ));
                    break;
                case 'terms':
                    if ($filter == 'marker' or $filter == 'sub_category' or $filter == 'category') {

                    } else {
                        $values = explode(',', $requested);
                        $values = array_filter($values);
                        $values = array_unique($values);
                        if ($filter == 'parent') {
                            $this->addQueryBoolFilter($filter, array(
                                'terms' => array(
                                    $filter . '.keyword' => $this->clearValues($values)
                                )
                            ));
                        } else {
                            $this->addPostFilterBoolFilter($filter, array(
                                'terms' => array(
                                    $filter . '.keyword' => $this->clearValues($values)
                                )
                            ));
                        }
                    }
                    break;
                default:
                    break;
            }
            #$this->filter_operations++;
        }

        $this->setFilterRanges($request);
        $this->setParents($request);

        #$this->setBoolean($request);

        $this->addQueryBoolFilter('published', array(
            'term' => array(
                'published' => true
            )
        ));
        return $this->getBody();
    }

    /**
     * @param string $filter
     * @param array $params
     */
    public function addQueryBoolFilter($filter, $params = [])
    {
        $this->body['query']['bool']['filter'][$filter] = $params;
        if (array_key_exists('terms', $params) and is_array($params['terms'])) {
            foreach ($params['terms'] as $field => $value) {
                $this->setSelectedValue($field, $value);
            }
        }

    }


    public function setKeyPostFilter($newKey)
    {
        if ($newKey == 'query') {
            $this->keyPostFilter = $newKey;
        }
    }

    /**
     * @param string $filter
     * @param $params
     */
    public function addPostFilterBoolFilter($filter, $params = [])
    {
        if (!isset($this->body[$this->keyPostFilter])) {
            $this->body = array(
                $this->keyPostFilter => array(
                    'bool' => array(
                        'filter' => array()
                    )
                )
            );
        }

        $this->body[$this->keyPostFilter]['bool']['filter'][$filter] = $params;
        if (!empty($params['terms']) and is_array($params['terms'])) {
            foreach ($params['terms'] as $field => $value) {
                $this->setSelectedValue($field, $value);
            }
        }
    }


    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * Вернет критерии запроса
     * @return array
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @return array
     */
    public function getSelections()
    {
        $selections = $this->selected;
        return $selections;
    }

    /**
     * @param $field
     * @return array|null
     */
    public function getSelected($field)
    {
        $selected = $this->getSelections();
        if (array_key_exists($field, $selected)) {
            return $selected[$field];
        }
        return null;
    }

    public function setSelectedValue($field, $value)
    {
        if (!is_array($value)) {
            echo '<pre>';
            print_r('is not array ' . $field);
            die;
        }

        $values = null;
        if (array_key_exists($field, $this->selected)) {
            $values = array_merge($this->selected[$field], $value);
        } else {
            $values = $value;
        }

        $this->selected[$field] = $this->clearValues($values);
    }


    /**
     * @param array $request
     * @return bool
     */
    public function setBoolean($request = [])
    {
        $sale = false;
        if (strripos($request['marker'], 'sale') !== false) {
            $sale = true;
        }
        if ($sale) {
            $this->addQueryBoolFilter('sale', array(
                'term' => array(
                    'sale' => true
                )
            ));
        }
        return true;
    }


    /**
     * @param array $request
     */
    public function setFilterRanges($request = [])
    {
        $fields = $this->es->getFields();
        $filterRanges = array();
        foreach ($fields as $field => $meta) {
            if ($meta['filter'] == 'range') {
                $filterRanges[$field] = 1;
            }
        }

        foreach ($request as $field => $values) {
            if (array_key_exists($field, $filterRanges)) {
                $values = explode(',', $values);
                $this->addPostFilterBoolFilter($field, array(
                    'range' => array(
                        $field => array(
                            'gte' => $values[0],
                            'lte' => $values[1],
                        )
                    )
                ));
            }
        }
    }

    /**
     * @param array $request
     * @return bool
     */
    public function setParents($request = [])
    {
        $requested = array();
        $mode = 'default';
        switch ($mode) {
            case 'default':
                $category_id = $this->es->isAjax() ? (int)$_POST['pageId'] : 0;
                $requested[] = $category_id;
                break;
            default:
                break;
        }
        if ((is_array($requested) and count($requested) == 1) and $requested[0] == 6) {
            return false;
        }
        return true;
    }


}