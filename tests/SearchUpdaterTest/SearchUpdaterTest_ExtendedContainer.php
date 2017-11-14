<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

/**
 * Used to test inherited ambiguous relationships.
 */
class SearchUpdaterTest_ExtendedContainer extends SearchUpdaterTest_OtherContainer
{
    private static $db = array(
        'SomeField' => 'Varchar',
    );
}
