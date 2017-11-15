<?php

namespace SilverStripe\FullTextSearch\Tests\SearchUpdaterTest;

use SilverStripe\ORM\DataObject;

class SearchUpdaterTest_ManyMany extends DataObject
{
    private static $db = array(
        'Field1' => 'Varchar',
        'Field2' => 'Varchar'
    );

    private static $table_name = 'SearchUpdaterTest_ManyMany';

    private static $belongs_many_many = array(
        'ManyManyContainer' => SearchUpdaterTest_Container::class,
        'ManyManyOtherContainer' => SearchUpdaterTest_OtherContainer::class,
    );
}
