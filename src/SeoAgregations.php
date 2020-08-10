<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 14.01.2020
 * Time: 22:21
 */

/*
 * Массив должен получатся вот такой
 *  'aggs' => array(
     'parent' => array(
         'filter' => array(
             'bool' => array(
                 'must' => array()
             )
         ),
         'aggs' => array(
             'parent' => array(
                 'terms' => array(
                     'field' => 'parent',
                     'size' => $size,
                     'exclude' => array(),
                     'order' => array(
                         '_count' => 'desc'
                     ),
                     'min_doc_count' => 0,
                 ),
                 "aggs" => array(
                     'colors' => array(
                         'terms' => array(
                             'field' => 'colors',
                             'size' => $size,
                             'exclude' => array(),
                             'order' => array(
                                 '_count' => 'desc'
                             ),
                             'min_doc_count' => 0,
                         ),
                         "aggs" => array(
                             'vendors' => array(
                                 'terms' => array(
                                     'field' => 'vendor',
                                     'size' => 100,
                                     'exclude' => array(),
                                     'order' => array(
                                         '_count' => 'desc'
                                     ),
                                     'min_doc_count' => 0,
                                 ),
                             )
                         )
                     )
                 )
             )
         )
     )
 )*/

namespace App\Elastic;

use modX;
use sfRule;
use PDO;
use pdoFetch;

class SeoAgregations
{
    /* @var int $size */
    private $size = 10000;

    /* @var int $min_doc_count */
    private $min_doc_count = 1;

    /* @var Search $es */
    public $es;

    /* @var array|null $aggregations */
    private $aggregations = null;

    public function __construct(Search $Search)
    {
        $this->es = $Search;
    }

    private function getAlias($field)
    {
        $aliases = array(
            'category' => 'parent',
            'region_id' => 'regions',
            'region' => 'regions',
        );
        if (array_key_exists($field, $aliases)) {
            return $aliases[$field];
        }
        return $field;
    }

    /* @var array|null $aliases */
    protected $aliases = null;

    /**
     * @return array
     */
    public function getAliases(int $rule_id)
    {
        if (is_null($this->aliases)) {

            $seofilter = $this->es->modx->getService('seofilter', 'seofilter', MODX_CORE_PATH . 'components/seofilter/model/seofilter/');

            /* @var sfRule $object */
            if ($Rule = $this->es->modx->getObject('sfRule', $rule_id)) {
                $q = $this->es->modx->newQuery('sfFieldIds');
                $q->where(array('multi_id' => $Rule->get('id')));
                $q->sortby('priority', 'ASC');
                $q->innerJoin('sfField', 'Field', 'Field.id = sfFieldIds.field_id');
                $q->select(array(
                    'Field.*'
                ));
                if ($q->prepare() && $q->stmt->execute()) {
                    while ($row = $q->stmt->fetch(PDO::FETCH_ASSOC)) {
                        $this->aliases[] = $this->getAlias($row['alias']);
                    }
                }
            }
        }
        return $this->aliases;
    }


    /**
     * @param $parent_id
     * @return array
     */
    public function getChildIds($parent_id)
    {
        /* @var pdoFetch $pdoFetch*/
        $pdoFetch = $this->es->modx->getService('pdoFetch');
        $children = $pdoFetch->getChildIds('modResource', $parent_id, 15, array(
            'context' => 'web',
            'where' => [
                'class_key' => 'msCategory'
            ]
        ));

        return $children;
    }


    /**
     * @param int $ruleId
     * @return array
     */
    public function get(int $ruleId, $parent = null)
    {
        $aliases = $this->getAliases($ruleId);
        $aggregations = $this->getAggregations($aliases);
        $params = $this->es->getDefaultParams();


        $params['body']['query'] = array(
            'bool' => array(
                'filter' => array(
                    0 => array(
                        'term' => array(
                            'published' => true
                        )
                    )
                )
            )
        );

        if (!empty($parent)) {
            $children = $this->getChildIds($parent);
            $children[] = $parent;
            $params['body']['query']['bool']['filter'][] = array(
                'terms' => array(
                    'parent' => $children
                )
            );
        }

        $params['body']['aggs'] = $aggregations;
        $response = $this->es->search($params);
        return $this->linkAssembly($aliases, $response['aggregations']);
    }

    /**
     * @param $aliases
     * @param $aggregations
     * @return array
     */
    private function linkAssembly($aliases, $aggregations)
    {
        $LinkAssembly = new LinkAssembly($this->es);
        return $LinkAssembly->get($aliases, $aggregations);
    }


    /**
     * @param $value
     * @return string
     */
    private function prefix($value)
    {
        return $value . '.keyword';
    }

    /**
     * @param $aggs
     * @param $aliases
     * @param int $i
     * @return mixed
     */
    private function aggs($aggs, $aliases, $i = 0)
    {
        if (!array_key_exists($i, $aliases)) {
            return $aggs;
        }

        $alias = $aliases[$i];
        if ($i == 0) {
            $aggs[$alias]['filter'] = array(
                'bool' => array(
                    'must' => array()
                )
            );
        }
        $aggs[$alias]['aggs'] = array(
            $alias => array(
                'terms' => array(
                    'field' => $this->prefix($alias),
                    'size' => $this->size,
                    'exclude' => array(),
                    'order' => array(
                        '_count' => 'desc'
                    ),
                    'min_doc_count' => $this->min_doc_count,
                ),
            )
        );

        // следующий
        $i++;
        if (array_key_exists($i, $aliases)) {
            $next_alias = $aliases[$i];
            $aggs[$alias]['aggs'][$alias]['aggs'][$next_alias] = $this->aggs(array(), $aliases, $i);
        }
        return $i > 1 ? $aggs[$alias]['aggs'][$alias] : $aggs;
    }


    /**
     * @param array $aliases
     * @param int $i
     * @return mixed
     */
    private function getAggregations($aliases = array(), $i = 0)
    {
        return $this->aggs(array(), $aliases);
    }
}