<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 12:39
 */

namespace App\Elastic;

use Elasticsearch\ClientBuilder;
use modX;
use xPDO;
use modCacheManager;

class Morpher
{
    /* @var modX $modx */
    public $modx;

    public function __construct()
    {
        $this->modx = modX::getInstance('modX');
    }

    public static function create()
    {
        return new static();
    }


    private $words = [];

    public function set($key, $value)
    {
        $this->words[$key] = $value;
    }

    public function get($key)
    {
        return null;
    }

    /**
     * @param $word
     * @param $token
     * @return array
     */
    public function request($word)
    {
        $update = (int)$this->get('update');
        if ($word && !$this->get('value_i')) {
            $this->set('value_i', $word);
        }
        if ($value = $this->morpher($word)) {
            if (!empty($value['Р']) && (!$this->get('value_r') || $update)) {
                $this->set('value_r', $value['Р']);
            }
            if (!empty($value['Д']) && (!$this->get('value_d') || $update)) {
                $this->set('value_d', $value['Д']);
            }
            if (!empty($value['В']) && (!$this->get('value_v') || $update)) {
                $this->set('value_v', $value['В']);
            }
            if (!empty($value['Т']) && (!$this->get('value_t') || $update)) {
                $this->set('value_t', $value['Т']);
            }
            if (!empty($value['П']) && (!$this->get('value_p') || $update)) {
                $this->set('value_p', $value['П']);
            }
            if (!empty($value['П-о']) && (!$this->get('value_o') || $update)) {
                $this->set('value_o', $value['П-о']);
            }
            if (!empty($value['где']) && (!$this->get('value_in') || $update)) {
                $this->set('value_in', $value['где']);
            }
            if (!empty($value['куда']) && (!$this->get('value_to') || $update)) {
                $this->set('value_to', $value['куда']);
            }
            if (!empty($value['откуда']) && (!$this->get('value_from') || $update)) {
                $this->set('value_from', $value['откуда']);
            }
            if (!empty($value['множественное']) && $values = (array)$value['множественное']) {
                if (!empty($values['И']) && (!$this->get('m_value_i') || $update)) {
                    $this->set('m_value_i', $values['И']);
                }
                if (!empty($values['Р']) && (!$this->get('m_value_r') || $update)) {
                    $this->set('m_value_r', $values['Р']);
                }
                if (!empty($values['Д']) && (!$this->get('m_value_d') || $update)) {
                    $this->set('m_value_d', $values['Д']);
                }
                if (!empty($values['В']) && (!$this->get('m_value_v') || $update)) {
                    $this->set('m_value_v', $values['В']);
                }
                if (!empty($values['Т']) && (!$this->get('m_value_t') || $update)) {
                    $this->set('m_value_t', $values['Т']);
                }
                if (!empty($values['П']) && (!$this->get('m_value_p') || $update)) {
                    $this->set('m_value_p', $values['П']);
                }
                if (!empty($values['П-о']) && (!$this->get('m_value_o') || $update)) {
                    $this->set('m_value_o', $values['П-о']);
                }
            }
        }
        return $this->words;
    }

    /**
     * @param string $text
     * @param bool $cache
     * @return false|mixed|string|null
     */
    private function morpher($text = '', $cache = true)
    {
        /* @var modCacheManager $cacheManager */
        if ($cache) {
            $key = md5($text);
            $optionsCache = array(
                xPDO::OPT_CACHE_KEY => 'default/elastic/morpher',
                xPDO::OPT_CACHE_HANDLER => 'xPDOFileCache'
            );
            $cacheManager = $this->modx->getCacheManager();
            $data = $cacheManager->get($key, $optionsCache);
            if (empty($data)) {
                $data = $this->send($text);
                if (!$response = $cacheManager->set($key, $data, 10000, $optionsCache)) {
                    return false;
                }
            }
        } else {
            $data = $this->send($text);
        }
        return $data;
    }

    /**
     * @param $text
     * @return mixed|string|null
     */
    private function send($text)
    {
        $token = $this->modx->getOption('seofilter_morpher_token');
        $url = 'https://ws3.morpher.ru/russian/declension?format=json&s=';
        $urlGet = $url . urlencode($text);

        if ($token) {
            $urlGet .= '&token=' . $token;
        }
        $out = '';
        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $urlGet);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_USERAGENT,
                "Mozilla/5.0(Windows;U;WindowsNT5.1;ru;rv:1.9.0.4)Gecko/2008102920AdCentriaIM/1.7Firefox/3.0.4");
            $out = curl_exec($curl);
        }
        $response = $this->modx->fromJSON($out);
        if (!empty($response['code']) && !empty($response['message'])) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR,
                '[SeoFilter] Morpher error: Code: ' . $response['code'] . '. Message: ' . $response['message']);
            return null;
        }
        return $response;
    }
}