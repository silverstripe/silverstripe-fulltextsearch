<?php

namespace SilverStripe\FullTextSearch\Search\Captures;

use SilverStripe\Dev\Deprecation;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\SQLite\SQLite3Database;

if (!class_exists(SQLite3Database::class)) {
    return;
}

/**
 * @deprecated 3.1.0 Use tractorcow/silverstripe-proxy-db to proxy the database connector instead
 */

class SearchManipulateCapture_SQLite3Database extends SQLite3Database
{

    public $isManipulationCapture = true;

    public function __construct()
    {
        Deprecation::notice('3.1.0', 'Use tractorcow/silverstripe-proxy-db to proxy the database connector instead', Deprecation::SCOPE_CLASS);
    }

    public function manipulate($manipulation)
    {
        $res = parent::manipulate($manipulation);
        SearchUpdater::handle_manipulation($manipulation);
        return $res;
    }
}
