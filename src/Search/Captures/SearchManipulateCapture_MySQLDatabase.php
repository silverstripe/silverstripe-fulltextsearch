<?php

namespace SilverStripe\FullTextSearch\Search\Captures;

use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * @deprecated 3.1.0 Use tractorcow/silverstripe-proxy-db to proxy the database connector instead
 */

class SearchManipulateCapture_MySQLDatabase extends MySQLDatabase
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
