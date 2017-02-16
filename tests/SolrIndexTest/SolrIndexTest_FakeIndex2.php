<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexTest_FakeIndex2 extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        $this->addFilterField('MyDate', 'Date');
        $this->addFilterField('HasOneObject.Field1');
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}
