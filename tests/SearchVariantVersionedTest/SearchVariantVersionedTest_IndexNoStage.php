<?php

namespace SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;

class SearchVariantVersionedTest_IndexNoStage extends SearchIndex_Recording
{
    public function init()
    {
        $this->addClass(SearchVariantVersionedTest_Item::class);
        $this->addFilterField('TestText');
        $this->excludeVariantState(array(SearchVariantVersioned::class => 'Stage'));
    }
}
