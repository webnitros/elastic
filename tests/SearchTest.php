<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 20:55
 */

use App\Elastic\Search;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    /* @var App\Elastic\Search $Search */
    public $Search;

    protected function setUp(): void
    {
        $this->Search = $Search = Search::create()->initialize('products', 'doc');
    }

    public function testInitialize()
    {
        $this->assertInstanceOf('App\Elastic\Search', $this->Search);
    }

    public function testGetIndexType()
    {
        $this->assertEquals($this->Search->getIndexType(), 'doc');
    }

    public function testSetFields()
    {
        $this->assertTrue($this->Search->setFields([
            'pagetitle' => array(
                'filter' => 'range',
                'type' => 'text',
                'aggs' => false
            ),
            'price' => array(
                'filter' => 'range',
                'type' => 'long',
                'aggs' => true
            )
        ]));

    }

    public function testGetAliases()
    {
        $this->assertTrue($this->Search->setFields([
            'pagetitle' => array(
                'filter' => 'range',
                'type' => 'text',
                'aggs' => false
            ),
            'price' => array(
                'filter' => 'range',
                'type' => 'long',
                'alias' => 'prices',
                'aggs' => true
            )
        ]));

        $this->assertEquals($this->Search->getAliases(), [
            'prices' => 'price'
        ]);
    }

    public function testGetFilterFields()
    {
        $this->assertTrue($this->Search->setFields([
            'pagetitle' => array(
                'filter' => 'range',
                'type' => 'text',
                'aggs' => false
            ),
            'price' => array(
                'filter' => 'range',
                'type' => 'long',
                'alias' => 'prices',
                'aggs' => true
            )
        ]));

        $this->assertEquals($this->Search->getFilterFields(), [
            'pagetitle' => 'range',
            'price' => 'range',
        ]);
    }

    public function testGetFields()
    {
        $fields = [
            'pagetitle' => array(
                'filter' => 'range',
                'type' => 'text',
                'aggs' => false
            ),
            'price' => array(
                'filter' => 'range',
                'type' => 'long',
                'alias' => 'prices',
                'aggs' => true
            )
        ];
        $this->assertTrue($this->Search->setFields($fields));
        $this->assertEquals($this->Search->getFields(), $fields);
    }

    public function testGetDefaultParams()
    {
        $params = $this->Search->getDefaultParams();
        $this->assertEquals($params['index'], 'products');
    }

    public function testGetField()
    {
        $this->assertTrue($this->Search->setFields([
            'pagetitle' => array(
                'filter' => 'range',
                'type' => 'text',
                'aggs' => false
            ),
            'price' => array(
                'filter' => 'range',
                'type' => 'long',
                'aggs' => true
            )
        ]));
        $this->assertEquals($this->Search->getField('pagetitle'), [
            'filter' => 'range',
            'type' => 'text',
            'aggs' => false
        ]);
    }

    public function testCreate()
    {
        $Search = $Search = Search::create();
        $this->assertInstanceOf('App\Elastic\Search', $Search);
    }

    public function testGetIndexName()
    {
        $this->assertEquals($this->Search->getIndexName(), 'products');
    }

    public function testGetCriteria()
    {
        $this->assertInstanceOf('App\Elastic\Criteria', $this->Search->criteria);
    }

    public function testSuggestions()
    {
        $this->assertInstanceOf('App\Elastic\Suggestions', $this->Search->suggestions);
    }

    public function testClient()
    {
        $this->assertInstanceOf('App\Elastic\Client', $this->Search->client);
    }

    public function testAggregations()
    {
        $this->assertInstanceOf('App\Elastic\Aggregations', $this->Search->aggregations);
    }
}
