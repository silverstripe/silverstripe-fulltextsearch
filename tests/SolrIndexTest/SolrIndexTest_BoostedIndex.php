<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrIndexTest_BoostedIndex extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        $this->addAllFulltextFields();
        $this->setFieldBoosting('SearchUpdaterTest_Container_Field1', 1.5);
        $this->addBoostedField('Field2', null, array(), 2.1);
    }
}
