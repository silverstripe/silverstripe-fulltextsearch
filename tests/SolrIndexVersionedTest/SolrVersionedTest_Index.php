<?php

namespace SilverStripe\FullTextSearch\Tests\SolrVersionedTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrVersionedTest_Index extends SolrIndex
{
    public function init()
    {
        $this->addClass('SearchVariantVersionedTest_Item');
        $this->addClass('SolrIndexVersionedTest_Object');
        $this->addFilterField('TestText');
        $this->addFulltextField('Content');
    }
}
