<?php

namespace SilverStripe\FullTextSearch\Captures;

use SilverStripe\SQLite\SQLite3Database;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

if (!class_exists('SQLite3Database')) {
    return;
}

class SearchManipulateCapture_SQLite3Database extends SQLite3Database
{

    public $isManipulationCapture = true;

    public function manipulate($manipulation)
    {
        $res = parent::manipulate($manipulation);
        SearchUpdater::handle_manipulation($manipulation);
        return $res;
    }
}
