<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexSubsitesTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexSubsitesTest_Index extends SolrIndex
{
    public function init()
    {
        $this->addClass('File');
        $this->addClass('SiteTree');
        $this->addAllFulltextFields();
    }
}
