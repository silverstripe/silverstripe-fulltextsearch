<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_ItemExtension;

/**
 * Does not have any variant extensions
 */
class SolrReindexTest_Item extends DataObject implements TestOnly
{
    private static $extensions = [
        SolrReindexTest_ItemExtension::class
    ];

    private static $db = array(
        'Title' => 'Varchar(255)',
        'Variant' => 'Int(0)'
    );
}
