<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;

class SearchUpdaterTest_Index extends SearchIndex_Recording
{
    public function init()
    {
        $this->addClass('SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container');

        $this->addFilterField('Field1');
        $this->addFilterField('HasOneObject.Field1');
        $this->addFilterField('HasManyObjects.Field1');
    }
}
