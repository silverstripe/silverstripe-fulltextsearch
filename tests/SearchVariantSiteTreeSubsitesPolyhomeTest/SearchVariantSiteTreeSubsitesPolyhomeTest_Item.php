<?php

namespace SilverStripe\FullTextSearch\Tests\SearchVariantSiteTreeSubsitesPolyhomeTest;

use SilverStripe\CMS\Model\SiteTree;

class SearchVariantSiteTreeSubsitesPolyhomeTest_Item extends SiteTree
{
    private static $table_name = 'SearchVariantSiteTreeSubsitesPolyhomeTest_Item';

    // TODO: Currently theres a failure if you addClass a non-table class
    private static $db = array(
        'TestText' => 'Varchar'
    );
}
