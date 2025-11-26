<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use Override;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_OtherContainer;

class SolrIndexTest_AmbiguousRelationIndex extends SolrIndex
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
        $this->addClass(SearchUpdaterTest_OtherContainer::class);

        // These relationships exist on both classes
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}
