<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasOne;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_ManyMany;

class SearchUpdaterTest_Container extends DataObject
{
    private static $db = [
        'Field1' => 'Varchar',
        'Field2' => 'Varchar',
        'MyDate' => 'Date',
    ];

    private static $table_name = 'SearchUpdaterTest_Container';

    private static $has_one = [
        'HasOneObject' => SearchUpdaterTest_HasOne::class
    ];

    private static $has_many = [
        'HasManyObjects' => SearchUpdaterTest_HasMany::class
    ];

    private static $many_many = [
        'ManyManyObjects' => SearchUpdaterTest_ManyMany::class
    ];
}
