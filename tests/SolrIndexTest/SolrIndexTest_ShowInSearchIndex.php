<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexTest_ShowInSearchIndex extends SolrIndex implements TestOnly
{
    public function init()
    {
        // adding a class here includes will include all subclasses, e.g. SolrIndexTest_MyDataObjectTwo
        $this->addClass(SolrIndexTest_MyDataObjectOne::class);
        $this->addClass(SiteTree::class);
        $this->addClass(File::class);
        $this->addFilterField('ShowInSearch');
    }
}
