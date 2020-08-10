<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 17:56
 */
namespace App\Elastic;

use modX;
use Exception;
use Elasticsearch\Client as ElasticClient;
use Elasticsearch\ClientBuilder;

class Client
{
    /* @var ElasticClient $connect */
    protected $conn = null;

    private function connect()
    {
        if (!$this->conn) {
            $this->conn = ClientBuilder::create()->setHosts(MODX_ELASTIC_HOSTS)->build();
        }
        return $this->conn;
    }

    /**
     * @param array $params
     * @return array|callable
     */
    protected function search($params = [])
    {
        return $this->connect()->search($params);
    }

    /**
     * @param $index
     * @return bool
     */
    protected function exists($params)
    {
        return $this->connect()->indices()->exists($params);
    }

    /**
     * @param $method
     * @param array $params
     * @return |null
     */
    public function process($method, $params = [])
    {
        $response = null;
        try {
            $response = $this->{$method}($params);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

}
