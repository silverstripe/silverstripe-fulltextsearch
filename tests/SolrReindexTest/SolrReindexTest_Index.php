<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrReindexTest_Index extends SolrIndex implements TestOnly
{
    public function init()
    {
        $this->addClass(SolrReindexTest_Item::class);
        $this->addAllFulltextFields();
    }
}
