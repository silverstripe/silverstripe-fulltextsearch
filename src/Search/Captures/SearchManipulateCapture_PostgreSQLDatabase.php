<?php

namespace SilverStripe\FullTextSearch\Search\Captures;

use SilverStripe\PostgreSQL\PostgreSQLDatabase;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

if (!class_exists(PostgreSQLDatabase::class)) {
    return;
}

/**
 * @deprecated 3.1...4.0 Please use tractorcow/silverstripe-proxy-db to proxy the database connector instead
 */

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
