<?php

namespace SilverStripe\FullTextSearch\Tests\SearchVariantSiteTreeSubsitesPolyhomeTest;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;

class SearchVariantSiteTreeSubsitesPolyhomeTest_Index extends SearchIndex_Recording
{
    public function init()
    {
        $this->addClass(SearchVariantSiteTreeSubsitesPolyhomeTest_Item::class);
        $this->addFilterField('TestText');
    }
}
