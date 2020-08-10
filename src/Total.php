<?php

namespace App\Elastic;

class Total
{
    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    public function getTotalElastic($params, $page_id = null)
    {
        $total = 0;
        $this->getCategories();
        $this->getShops();

        // Если подсчет ведется персонально для категории
        if (is_numeric($page_id) and $page_id != 6) {
            $params['category'] = $page_id;
        }

        if ($Elastic = loadElastic()) {

            $Elastic->debug();

            $q = $Elastic->newRequest(false);
            $q->select('id');

            $q->addFilter('published', true);
            $q->addFilter('defective', false);


            // В случае если регион не назначен ищим любой остаток в любом магазине
            if (!array_key_exists('region', $params)) {
                // Уцененные товары создают проблему из за которой пересчет становится не совсем точный так как их мы показываем только в Москве
                $q->addFilter('availability', true);
            }

            foreach ($params as $key => $old_value) {
                $val = explode(',', $old_value);
                switch ($key) {
                    case 'category':
                        $key = 'parent';
                        if (array_key_exists($old_value, $this->categories)) {
                            $val = $this->categories[$old_value];
                        }

                        break;
                    case 'region':
                        if (array_key_exists($old_value, $this->shops)) {
                            $val = $this->shops[$old_value];
                        }
                        $key = 'shop_availability';
                        break;
                    case 'shop':
                        $val = array($old_value);
                        $key = 'shop_availability';
                        break;
                    default:
                        break;
                }
                $q->addFilter($key, $val);
            }
            $q->preapre();
            $total = $q->count();
        }
        return $total;

    }

}