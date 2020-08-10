<?php

namespace App\Elastic;

class Suggestions
{
    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    /**
     * Вернет количество для каждого значения из агрегаций
     * @param array $aggregations
     * @return array
     */
    public function get($aggregations = array())
    {
        $suggestions = array();
        $fields = $this->es->getFields();
        foreach ($aggregations as $key => $aggregation) {
            $field = null;
            if (is_null($field)) {
                $field = $key;
            }

            $meta = $fields[$field];
            $filter = $meta['filter'];

            $values = null;
            switch ($filter) {
                case 'terms':
                    $keyTmp = $key . '.keyword';
                    if (array_key_exists($keyTmp, $aggregation)) {
                        $buckets = $aggregation[$keyTmp];
                        $buckets_selected = $aggregation[$key . '_selected'];
                        if (!empty($buckets['buckets'])) {
                            foreach ($buckets['buckets'] as $bucket) {
                                if (empty($bucket['key'])) {
                                    continue;
                                }
                                $value = !empty($bucket['key']) ? $bucket['key'] : 'Нет значени';
                                $doc_count = $bucket['doc_count'];
                                $values[$value] = $doc_count;
                            }
                        }
                        if (!empty($buckets_selected['buckets'])) {
                            foreach ($buckets_selected['buckets'] as $bucket) {
                                if (empty($bucket['key'])) {
                                    continue;
                                }
                                $value = !empty($bucket['key']) ? $bucket['key'] : 'Нет значени';
                                $doc_count = $bucket['doc_count'];
                                $values[$value] = $doc_count;
                            }
                        }

                        $suggestions[$key] = is_array($values) ? $values : [];
                    }
                    break;
                case 'range':
                    $doc_count = $aggregation['doc_count'];
                    $vmin = (int)$aggregation[$field . '_min']['value'];
                    $vmax = (int)$aggregation[$field . '_max']['value'];
                    $suggestions[$field][$vmin] = $doc_count;
                    $suggestions[$field][$vmax] = $doc_count;
                    break;

                default:
                    break;
            }
            if ($values) {
                $suggestions[$key] = $values;
            }
        }
        return $suggestions;
    }

}