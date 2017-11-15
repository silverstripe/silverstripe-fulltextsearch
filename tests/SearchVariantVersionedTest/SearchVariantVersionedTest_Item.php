<?php

namespace SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class SearchVariantVersionedTest_Item extends SiteTree implements TestOnly
{
    private static $table_name = 'SearchVariantVersionedTest_Item';

    // TODO: Currently theres a failure if you addClass a non-table class
    private static $db = array(
        'TestText' => 'Varchar'
    );
}
