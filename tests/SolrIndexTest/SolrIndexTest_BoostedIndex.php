<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use Override;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;

class SolrIndexTest_BoostedIndex extends SolrIndex
{
    #[Override]
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass(SearchUpdaterTest_Container::class);
        $this->addAllFulltextFields();
        $this->setFieldBoosting(SearchUpdaterTest_Container::class . '_Field1', 1.5);
        $this->addBoostedField('Field2', null, [], 2.1);
    }
}
