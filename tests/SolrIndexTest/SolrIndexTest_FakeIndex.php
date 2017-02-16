<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexTest_FakeIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');

        $this->addFilterField('Field1');
        $this->addFilterField('MyDate', 'Date');
        $this->addFilterField('HasOneObject.Field1');
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}
