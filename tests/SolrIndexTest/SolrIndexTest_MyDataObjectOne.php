<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class SolrIndexTest_MyDataObjectOne extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)',
        'ShowInSearch' => 'Boolean',
        'CanViewValue' => 'Boolean(true)',
    ];

    private static $table_name = 'SolrIndexTestMyDataObjectOne';

    public function canView($member = null)
    {
        return $this->CanViewValue;
    }
}
