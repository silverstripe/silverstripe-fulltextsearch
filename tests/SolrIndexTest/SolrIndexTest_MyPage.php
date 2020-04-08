<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use Page;
use SilverStripe\Dev\TestOnly;

class SolrIndexTest_MyPage extends Page implements TestOnly
{
    public function getShowInSearch()
    {
        return true;
    }
}
