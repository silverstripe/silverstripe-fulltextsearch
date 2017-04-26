<?php

namespace SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;

class SearchVariantVersionedTest_Index extends SearchIndex_Recording
{
    public function init()
    {
        $this->addClass(SearchVariantVersionedTest_Item::class);
        $this->addFilterField('TestText');
    }
}
