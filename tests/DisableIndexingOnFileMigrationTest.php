<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Extensions\DisableIndexingOnFileMigration;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * Logger for recording messages for later retrieval
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
