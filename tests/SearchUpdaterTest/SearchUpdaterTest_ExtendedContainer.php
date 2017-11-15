<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

/**
 * Used to test inherited ambiguous relationships.
 */
class SearchUpdaterTest_ExtendedContainer extends SearchUpdaterTest_OtherContainer
{
    private static $table_name = 'SearchUpdaterTest_ExtendedContainer';

    private static $db = [
        'SomeField' => 'Varchar',
    ];
}
