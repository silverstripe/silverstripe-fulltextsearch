<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Does not have any variant extensions
 */
class SolrReindexTest_Item extends DataObject implements TestOnly
{
    private static $extensions = array(
        'SolrReindexTest_ItemExtension'
    );

    private static $db = array(
        'Title' => 'Varchar(255)',
        'Variant' => 'Int(0)'
    );
}
