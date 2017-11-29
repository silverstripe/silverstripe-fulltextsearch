<?php

namespace SilverStripe\FullTextSearch\Captures;

use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

class SearchManipulateCapture_MySQLDatabase extends MySQLDatabase
{

    public $isManipulationCapture = true;

    public function manipulate($manipulation)
    {
        $res = parent::manipulate($manipulation);
        SearchUpdater::handle_manipulation($manipulation);
        return $res;
    }
}
