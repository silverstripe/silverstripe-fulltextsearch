<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_ExtendedContainer;

class SolrIndexTest_AmbiguousRelationInheritedIndex extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass(SearchUpdaterTest_Container::class);
        // this one has not the relation defined in it's class but is rather inherited from parent
        // note that even if we do not include it's parent class the fields will be properly added
        $this->addClass(SearchUpdaterTest_ExtendedContainer::class);

        // These relationships exist on both classes
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}
