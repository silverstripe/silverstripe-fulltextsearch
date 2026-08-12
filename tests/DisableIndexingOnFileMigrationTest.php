<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Extensions\DisableIndexingOnFileMigration;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * Tests that indexing is disabled while a file migration runs
 */
class DisableIndexingOnFileMigrationTest extends SapphireTest
{

    public function testPreFileMigration()
    {
        $this->assertTrue(SearchUpdater::config()->get('enabled'));

        Injector::inst()->get(DisableIndexingOnFileMigration::class)->preFileMigration();

        $this->assertFalse(SearchUpdater::config()->get('enabled'));
    }
}
