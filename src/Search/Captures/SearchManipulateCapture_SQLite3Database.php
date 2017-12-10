<?php

namespace SilverStripe\FullTextSearch\Search\Captures;

use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\SQLite\SQLite3Database;

if (!class_exists(SQLite3Database::class)) {
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
