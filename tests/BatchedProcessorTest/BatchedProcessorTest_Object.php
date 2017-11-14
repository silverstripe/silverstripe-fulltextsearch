<?php

namespace SilverStripe\FullTextSearch\Tests\BatchedProcessorTest;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class BatchedProcessorTest_Object extends SiteTree implements TestOnly
{
    private static $table_name = 'BatchedProcessorTest_Object';

    private static $db = array(
        'TestText' => 'Varchar'
    );
}
