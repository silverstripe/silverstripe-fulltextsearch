<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class SearchableServiceTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setup();
        SearchableService::singleton()->clearCache();
    }

    public function testIsIndexable()
    {
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);

        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);

        Member::actAs(null, function () {
            $searchableService = SearchableService::singleton();

            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 1;
            $page->write();
            $this->assertTrue($searchableService->isIndexable($page));

            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 0;
            $page->write();
            $this->assertFalse($searchableService->isIndexable($page));
        });
    }

    public function testIsViewable()
    {
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);

        Member::actAs(null, function () {
            $searchableService = SearchableService::singleton();

            $page = SiteTree::create();
            $page->CanViewType = 'Anyone';
            $page->ShowInSearch = 1;
            $page->write();
            $this->assertTrue($searchableService->isViewable($page));

            $page = SiteTree::create();
            $page->CanViewType = 'LoggedInUsers';
            $page->ShowInSearch = 1;
            $page->write();
            $this->assertFalse($searchableService->isViewable($page));
        });
    }

    public function testClearCache()
    {
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);

        $searchableService = SearchableService::singleton();

        $page = SiteTree::create();
        $page->CanViewType = 'Anyone';
        $page->ShowInSearch = 0;
        $page->write();
        $this->assertFalse($searchableService->isIndexable($page));

        // test the results are cached (expect stale result)
        $page->ShowInSearch = 1;
        $page->write();
        $this->assertFalse($searchableService->isIndexable($page));

        // after clearing cache, expect fresh result
        $searchableService->clearCache();
        $this->assertTrue($searchableService->isIndexable($page));
    }

    public function testSkipIndexingCanViewCheck()
    {
        $searchableService = SearchableService::singleton();
        $page = SiteTree::create();
        $page->CanViewType = 'LoggedInUsers';
        $page->ShowInSearch = 1;
        $page->write();
        $this->assertFalse($searchableService->isIndexable($page));

        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);
        $searchableService->clearCache();
        $this->assertTrue($searchableService->isIndexable($page));
    }

    public function testVariantStateExcluded()
    {
        $searchableService = SearchableService::singleton();
        $variantStateDraft = [SearchVariantVersioned::class => Versioned::DRAFT];
        $variantStateLive = [SearchVariantVersioned::class => Versioned::LIVE];

        // default variant_state_draft_excluded = true
        $this->assertTrue($searchableService->variantStateExcluded($variantStateDraft));
        $this->assertFalse($searchableService->variantStateExcluded($variantStateLive));

        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);
        $this->assertFalse($searchableService->variantStateExcluded($variantStateDraft));
        $this->assertFalse($searchableService->variantStateExcluded($variantStateLive));
    }
}
