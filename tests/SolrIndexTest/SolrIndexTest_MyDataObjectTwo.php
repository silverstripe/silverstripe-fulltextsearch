<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\Dev\TestOnly;

class SolrIndexTest_MyDataObjectTwo extends SolrIndexTest_MyDataObjectOne implements TestOnly
{
    private static $table_name = 'SolrIndexTestMyDataObjectTwo';

    public function getShowInSearch()
    {
        return true;
    }
}
