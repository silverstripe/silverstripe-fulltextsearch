<?php

namespace SilverStripe\FullTextSearch\Search\Captures;

use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * @deprecated 3.1...4.0 Please use tractorcow/silverstripe-proxy-db to proxy the database connector instead
 */

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
