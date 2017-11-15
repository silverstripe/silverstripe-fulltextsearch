<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;

class SearchUpdaterTest_HasMany extends DataObject
{
    private static $db = array(
        'Field1' => 'Varchar',
        'Field2' => 'Varchar'
    );

    private static $table_name = 'SearchUpdaterTest_HasMany';

    private static $has_one = array(
        'HasManyContainer' => SearchUpdaterTest_Container::class,
        'HasManyOtherContainer' => SearchUpdaterTest_OtherContainer::class,
    );
}
