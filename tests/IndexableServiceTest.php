<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Services\IndexableService;

class IndexableServiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function setup()
    {
        parent::setup();
        IndexableService::singleton()->clearCache();
    }

    public function testIsIndexable()
    {
        $indexableService = IndexableService::singleton();

        $page = SiteTree::create();
        $page->CanViewType = 'Anyone';
        $page->ShowInSearch = 1;
        $page->write();
        $this->assertTrue($indexableService->isIndexable($page));

        $page = SiteTree::create();
        $page->CanViewType = 'Anyone';
        $page->ShowInSearch = 0;
        $page->write();
        $this->assertFalse($indexableService->isIndexable($page));
    }

    public function testClearCache()
    {
        $indexableService = IndexableService::singleton();

        $page = SiteTree::create();
        $page->CanViewType = 'Anyone';
        $page->ShowInSearch = 0;
        $page->write();
        $this->assertFalse($indexableService->isIndexable($page));

        // test the results are cached (expect stale result)
        $page->ShowInSearch = 1;
        $page->write();
        $this->assertFalse($indexableService->isIndexable($page));

        // after clearing cache, expect fresh result
        $indexableService->clearCache();
        $this->assertTrue($indexableService->isIndexable($page));
    }
}
