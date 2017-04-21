<?php

namespace SilverStripe\FullTextSearch\Tests\SolrVersionedTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 * Non-sitetree versioned dataobject
 */
class SolrIndexVersionedTest_Object extends DataObject implements TestOnly {

    private static $extensions = array(
        'Versioned'
    );

    private static $db = array(
        'Title' => 'Varchar',
        'Content' => 'Text',
        'TestText' => 'Varchar',
    );
}
