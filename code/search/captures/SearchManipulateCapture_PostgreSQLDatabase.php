<?php

namespace SilverStripe\FullTextSearch\Captures;

use SilverStripe\PostgreSQL\PostgreSQLDatabase;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

if (!class_exists('PostgreSQLDatabase')) {
    return;
}

class SearchManipulateCapture_PostgreSQLDatabase extends PostgreSQLDatabase
{

    public $isManipulationCapture = true;

    public function manipulate($manipulation)
    {
        $res = parent::manipulate($manipulation);
        SearchUpdater::handle_manipulation($manipulation);
        return $res;
    }
}
