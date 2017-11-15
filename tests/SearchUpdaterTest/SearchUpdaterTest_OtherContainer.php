<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\ORM\DataObject;

/**
 * Used to test ambiguous relationships.
 */
class SearchUpdaterTest_OtherContainer extends DataObject
{
    private static $table_name = 'SearchUpdaterTest_OtherContainer';

    private static $has_many = [
        'HasManyObjects' => SearchUpdaterTest_HasMany::class,
    ];

    private static $many_many = [
        'ManyManyObjects' => SearchUpdaterTest_ManyMany::class,
    ];
}
