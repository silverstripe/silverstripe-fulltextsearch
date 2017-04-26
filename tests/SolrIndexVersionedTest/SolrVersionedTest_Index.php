<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrIndexVersionedTest_Object;

class SolrVersionedTest_Index extends SolrIndex
{
    public function init()
    {
        $this->addClass(SearchVariantVersionedTest_Item::class);
        $this->addClass(SolrIndexVersionedTest_Object::class);
        $this->addFilterField('TestText');
        $this->addFulltextField('Content');
    }
}
