<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexSubsitesTest;

use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexSubsitesTest_Index extends SolrIndex
{
    public function init()
    {
        $this->addClass(File::class);
        $this->addClass(SiteTree::class);
        $this->addAllFulltextFields();
    }
}
