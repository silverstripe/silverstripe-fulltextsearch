<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Versioned;

/**
 * Non-sitetree versioned dataobject
 */
class SolrIndexVersionedTest_Object extends DataObject implements TestOnly
{

    private static $table_name = 'SolrIndexVersionedTest_Object';

    private static $extensions = [
        Versioned::class
    ];

    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'Text',
        'TestText' => 'Varchar',
    ];
}
