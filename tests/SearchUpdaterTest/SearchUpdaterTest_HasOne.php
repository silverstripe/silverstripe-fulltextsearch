<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;

class SearchUpdaterTest_HasOne extends DataObject
{
    private static $db = array(
        'Field1' => 'Varchar',
        'Field2' => 'Varchar'
    );

    private static $table_name = 'SearchUpdaterTest_HasOne';

    private static $has_many = array(
        'HasManyContainers' => SearchUpdaterTest_Container::class,
        'HasManyOtherContainer' => SearchUpdaterTest_OtherContainer::class,
    );
}
