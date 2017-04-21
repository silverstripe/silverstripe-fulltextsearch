<?php
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
global $databaseConfig;
if (isset($databaseConfig['type'])) SearchUpdater::bind_manipulation_capture();
