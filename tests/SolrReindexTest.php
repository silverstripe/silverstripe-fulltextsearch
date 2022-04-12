<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use Page;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexImmediateHandler;
use SilverStripe\FullTextSearch\Solr\Services\Solr4Service;
use SilverStripe\FullTextSearch\Solr\Services\SolrService;
use SilverStripe\FullTextSearch\Solr\Tasks\Solr_Reindex;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectOne;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectTwo;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyPage;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_ShowInSearchIndex;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Index;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Item;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_RecordingLogger;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_TestHandler;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Variant;
use SilverStripe\Versioned\Versioned;

class SolrReindexTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected static $extra_dataobjects = array(
        SolrReindexTest_Item::class,
        SolrIndexTest_MyPage::class,
        SolrIndexTest_MyDataObjectOne::class,
        SolrIndexTest_MyDataObjectTwo::class,
    );

    /**
     * Forced index for testing
     *
     * @var SolrReindexTest_Index
     */
    protected $index = null;

    /**
     * Mock service
     *
     * @var SolrService
     */
    protected $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test handler for reindex
        Config::modify()->set(Injector::class, SolrReindexHandler::class, array(
            'class' => SolrReindexTest_TestHandler::class
        ));

        Injector::inst()->registerService(new SolrReindexTest_TestHandler(), SolrReindexHandler::class);

        // Set test variant
        SolrReindexTest_Variant::enable();

        // Set index list
        $this->service = $this->getServiceMock();
        $this->index = singleton(SolrReindexTest_Index::class);
        $this->index->setService($this->service);

        FullTextSearch::force_index_list($this->index);
    }

    /**
     * Populate database with dummy dataset
     *
     * @param int $number Number of records to create in each variant
     */
    protected function createDummyData($number)
    {
        // Note that we don't create any records in variant = 2, to represent a variant
        // that should be cleared without any re-indexes performed
        foreach ([0, 1] as $variant) {
            for ($i = 1; $i <= $number; $i++) {
                $item = new SolrReindexTest_Item();
                $item->Variant = $variant;
                $item->Title = "Item $variant / $i";
                $item->write();
            }
        }
    }

    /**
     * Mock service
     *
     * @return SolrService
     */
    protected function getServiceMock()
    {
        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['deleteByQuery', 'addDocument']);

        return $serviceMock->getMock();
    }

    protected function tearDown(): void
    {
        FullTextSearch::force_index_list();
        SolrReindexTest_Variant::disable();
        parent::tearDown();
    }

    /**
     * Get the reindex handler
     *
     * @return SolrReindexHandler
     */
    protected function getHandler()
    {
        return Injector::inst()->get(SolrReindexHandler::class);
    }

    /**
     * Ensure the test variant is up and running properly
     */
    public function testVariant()
    {
        // State defaults to 0
        $variant = SearchVariant::current_state();
        $this->assertEquals(
            array(
                SolrReindexTest_Variant::class => "0"
            ),
            $variant
        );

        // All states enumerated
        $allStates = iterator_to_array(SearchVariant::reindex_states());
        $this->assertEquals(
            array(
                array(
                    SolrReindexTest_Variant::class => "0"
                ),
                array(
                    SolrReindexTest_Variant::class => "1"
                ),
                array(
                    SolrReindexTest_Variant::class => "2"
                )
            ),
            $allStates
        );

        // Check correct items created and that filtering on variant works
        $this->createDummyData(120);
        SolrReindexTest_Variant::set_current(2);
        $this->assertEquals(0, SolrReindexTest_Item::get()->count());
        SolrReindexTest_Variant::set_current(1);
        $this->assertEquals(120, SolrReindexTest_Item::get()->count());
        SolrReindexTest_Variant::set_current(0);
        $this->assertEquals(120, SolrReindexTest_Item::get()->count());
        SolrReindexTest_Variant::disable();
        $this->assertEquals(240, SolrReindexTest_Item::get()->count());
    }

    /**
     * Given the invocation of a new re-index with a given set of data, ensure that the necessary
     * list of groups are created and segmented for each state
     *
     * Test should work fine with any variants (versioned, subsites, etc) specified
     */
    public function testReindexSegmentsGroups()
    {
        $this->service->method('deleteByQuery')
            ->withConsecutive(
                ['-(ClassHierarchy:' . SolrReindexTest_Item::class . ')'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +(_testvariant:"2")']
            );

        $this->createDummyData(120);

        // Initiate re-index
        $logger = new SolrReindexTest_RecordingLogger();
        $this->getHandler()->runReindex($logger, 21, Solr_Reindex::class);

        // Test that invalid classes are removed
        $this->assertContains(
            'Clearing obsolete classes from ' . str_replace('\\', '-', SolrReindexTest_Index::class),
            $logger->getMessages()
        );

        // Test that valid classes in invalid variants are removed
        $this->assertContains(
            'Clearing all records of type ' . SolrReindexTest_Item::class . ' in the current state: {'
            . json_encode(SolrReindexTest_Variant::class) . ':"2"}',
            $logger->getMessages()
        );

        // 120x2 grouped into groups of 21 results in 12 groups
        $this->assertEquals(12, $logger->countMessages('Called processGroup with '));
        $this->assertEquals(6, $logger->countMessages('{' . json_encode(SolrReindexTest_Variant::class) . ':"0"}'));
        $this->assertEquals(6, $logger->countMessages('{' . json_encode(SolrReindexTest_Variant::class) . ':"1"}'));

        // Given that there are two variants, there should be two group ids of each number
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 0 of 6'));
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 1 of 6'));
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 2 of 6'));
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 3 of 6'));
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 4 of 6'));
        $this->assertEquals(2, $logger->countMessages(' ' . SolrReindexTest_Item::class . ', group 5 of 6'));

        // Check various group sizes
        $logger->clear();
        $this->getHandler()->runReindex($logger, 120, 'Solr_Reindex');
        $this->assertEquals(2, $logger->countMessages('Called processGroup with '));
        $logger->clear();
        $this->getHandler()->runReindex($logger, 119, 'Solr_Reindex');
        $this->assertEquals(4, $logger->countMessages('Called processGroup with '));
        $logger->clear();
        $this->getHandler()->runReindex($logger, 121, 'Solr_Reindex');
        $this->assertEquals(2, $logger->countMessages('Called processGroup with '));
        $logger->clear();
        $this->getHandler()->runReindex($logger, 2, 'Solr_Reindex');
        $this->assertEquals(120, $logger->countMessages('Called processGroup with '));
    }

    /**
     * Test index processing on individual groups
     */
    public function testRunGroup()
    {
        $classesToSkip = [SolrReindexTest_Item::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);

        $this->service->method('deleteByQuery')
            ->with('+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=2 u=2}mod(ID, 6)" +(_testvariant:"1")');

        $this->createDummyData(120);
        $logger = new SolrReindexTest_RecordingLogger();

        // Initiate re-index of third group (index 2 of 6)
        $state = array(SolrReindexTest_Variant::class => '1');
        $this->getHandler()->runGroup($logger, $this->index, $state, SolrReindexTest_Item::class, 6, 2);
        $idMessage = $logger->filterMessages('Updated ');
        $this->assertNotEmpty(preg_match('/^Updated (?<ids>[,\d]+)/i', $idMessage[0] ?? '', $matches));
        $ids = array_unique(explode(',', $matches['ids'] ?? ''));

        // Test successful
        $this->assertNotEmpty($logger->getMessages('Adding ' . SolrReindexTest_Item::class));
        $this->assertNotEmpty($logger->getMessages('Done'));

        // Test that items in this variant / group are re-indexed
        // 120 divided into 6 groups should be 20 at least (max 21)
        $c = count($ids ?? []);
        $this->assertTrue($c === 20 || $c === 21, 'Group size is about 20');
        foreach ($ids as $id) {
            // Each id should be % 6 == 2
            $this->assertEquals(2, $id % 6, "ID $id Should match pattern ID % 6 = 2");
        }
    }

    /**
     * Test that running all groups covers the entire range of dataobject IDs
     */
    public function testRunAllGroups()
    {
        $classesToSkip = [SolrReindexTest_Item::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);

        $this->service->method('deleteByQuery')
            ->withConsecutive(
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=0 u=0}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=1 u=1}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=2 u=2}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=3 u=3}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=4 u=4}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=5 u=5}mod(ID, 6)" +(_testvariant:"1")'],
                ['+(ClassHierarchy:' . SolrReindexTest_Item::class . ') +_query_:"{!frange l=6 u=6}mod(ID, 6)" +(_testvariant:"1")']
            );

        $this->createDummyData(120);
        $logger = new SolrReindexTest_RecordingLogger();

        // Test that running all groups covers the complete set of ids
        $state = array(SolrReindexTest_Variant::class => '1');
        for ($i = 0; $i < 6; $i++) {
            // See testReindexSegmentsGroups for test that each of these states is invoked during a full reindex
            $this
                ->getHandler()
                ->runGroup($logger, $this->index, $state, SolrReindexTest_Item::class, 6, $i);
        }

        // Count all ids updated
        $ids = array();
        foreach ($logger->filterMessages('Updated ') as $message) {
            $this->assertNotEmpty(preg_match('/^Updated (?<ids>[,\d]+)/', $message ?? '', $matches));
            $ids = array_unique(array_merge($ids, explode(',', $matches['ids'] ?? '')));
        }

        // Check ids
        $this->assertEquals(120, count($ids ?? []));
    }

    /**
     * Test that ShowInSearch filtering is working correctly
     */
    public function testShowInSearch()
    {
        // allow anonymous users to assess draft-only content to pass canView() check (will auto-reset for next test)
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page true';
        $pageA->ShowInSearch = true;
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page false';
        $page->ShowInSearch = false;
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File true';
        $fileA->ShowInSearch = true;
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File false';
        $file->ShowInSearch = false;
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->ShowInSearch = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->ShowInSearch = false;
        $objOne->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $objTwoA = new SolrIndexTest_MyDataObjectTwo();
        $objTwoA->Title = 'Test MyDataObjectTwo false';
        $objTwoA->ShowInSearch = false;
        $objTwoA->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $myPageA = new SolrIndexTest_MyPage();
        $myPageA->Title = 'Test MyPage false';
        $myPageA->ShowInSearch = false;
        $myPageA->write();

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['addDocument', 'deleteByQuery'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $myPageA, $fileA, $objOneA, $objTwoA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                SolrIndexTest_MyPage::class . $myPageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID,
                SolrIndexTest_MyDataObjectTwo::class . $objTwoA->ID
            ];
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        $serviceMock
            ->expects($this->exactly(5))
            ->method('addDocument')
            ->withConsecutive(
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)]
            );

        $logger = new SolrReindexTest_RecordingLogger();
        $state = [SearchVariantVersioned::class => Versioned::DRAFT];
        $handler = Injector::inst()->get(SolrReindexImmediateHandler::class);
        $handler->runGroup($logger, $index, $state, SiteTree::class, 1, 0);
        $handler->runGroup($logger, $index, $state, File::class, 1, 0);
        $handler->runGroup($logger, $index, $state, SolrIndexTest_MyDataObjectOne::class, 1, 0);
    }

    /**
     * Test that CanView filtering is working correctly
     */
    public function testCanView()
    {
        // allow anonymous users to assess draft-only content to pass canView() check (will auto-reset for next test)
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page Anyone';
        $pageA->CanViewType = 'Anyone';
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page LoggedInUsers';
        $page->CanViewType = 'LoggedInUsers';
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File Anyone';
        $fileA->CanViewType = 'Anyone';
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File LoggedInUsers';
        $file->CanViewType = 'LoggedInUsers';
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->CanViewValue = true;
        $objOneA->ShowInSearch = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->CanViewValue = false;
        $objOneA->ShowInSearch = true;
        $objOne->write();

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['addDocument', 'deleteByQuery'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $fileA, $objOneA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID,
            ];
            $solrDocKey = $this->createSolrDocKey($doc);
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        $serviceMock
            ->expects($this->exactly(3))
            ->method('addDocument')
            ->withConsecutive(
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)]
            );

        $logger = new SolrReindexTest_RecordingLogger();
        $state = [SearchVariantVersioned::class => Versioned::DRAFT];
        $handler = Injector::inst()->get(SolrReindexImmediateHandler::class);
        $handler->runGroup($logger, $index, $state, SiteTree::class, 1, 0);
        $handler->runGroup($logger, $index, $state, File::class, 1, 0);
        $handler->runGroup($logger, $index, $state, SolrIndexTest_MyDataObjectOne::class, 1, 0);
    }

    protected function createSolrDocKey(Apache_Solr_Document $doc)
    {
        return $doc->getField('ClassName')['value'] . $doc->getField('ID')['value'];
    }
}
