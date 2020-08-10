<?php
/**
 * Created by Andrey Stepanenko.
 * User: webnitros
 * Date: 10.08.2020
 * Time: 21:32
 */

use App\Elastic\Search;
use App\Elastic\Criteria;
use PHPUnit\Framework\TestCase;

class CriteriaTest extends TestCase
{
    /* @var App\Elastic\Search $Search */
    public $Search;

    protected function setUp(): void
    {
        $this->Search = $Search = Search::create()->initialize('products', 'doc');
        $this->Search->setFields([
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
        ]);

    }


    public function testProcess()
    {

    }

    public function testGetSelected()
    {

    }

    public function testClearValues()
    {

    }

    public function testGetCriteria()
    {

    }

    public function testSetKeyPostFilter()
    {

    }

    public function testAddQueryBoolFilter()
    {

    }

    public function testGetPostCriteria()
    {

    }

    public function testSetParents()
    {

    }

    public function testReset()
    {

    }

    public function testGetBody()
    {

    }

    public function testGetDefaultRequest()
    {

    }

    public function testSetParent()
    {

    }

    public function testSetRequest()
    {

    }

    public function testSetSelectedValue()
    {

    }

    public function testSetBoolean()
    {
        $this->Search->criteria->setBoolean([
            'published' => true
        ]);
    }

    public function testGetSelections()
    {

    }

    public function testSetFilterRanges()
    {

    }
}
