<?php

use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

if (isset($databaseConfig['type'])) {
    SearchUpdater::bind_manipulation_capture();
}
