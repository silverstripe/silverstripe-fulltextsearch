<?php

use SilverStripe\Dev\SapphireTest;

use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Variant;

if (class_exists('Phockito')) {
    Phockito::include_hamcrest(false);
}

class SolrReindexTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected $extraDataObjects = array(
        'SolrReindexTest_Item'
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

    public function setUp()
    {
        parent::setUp();

        if (!class_exists('Phockito')) {
            $this->skipTest = true;
            return $this->markTestSkipped("These tests need the Phockito module installed to run");
        }

        // Set test handler for reindex
        Config::modify()->set('Injector', 'SolrReindexHandler', array(
            'class' => 'SolrReindexTest_TestHandler'
        ));
        Injector::inst()->registerService(new SolrReindexTest_TestHandler(), 'SolrReindexHandler');

        // Set test variant
        SolrReindexTest_Variant::enable();

        // Set index list
        $this->service = $this->getServiceMock();
        $this->index = singleton('SolrReindexTest_Index');
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
        // Populate dataobjects. Use truncate to generate predictable IDs
        DB::query('TRUNCATE "SolrReindexTest_Item"');

        // Note that we don't create any records in variant = 2, to represent a variant
        // that should be cleared without any re-indexes performed
        foreach (array(0, 1) as $variant) {
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
        return Phockito::mock('Solr4Service');
    }

    public function tearDown()
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
        return Injector::inst()->get('SolrReindexHandler');
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
                "SolrReindexTest_Variant" => "0"
            ),
            $variant
        );

        // All states enumerated
        $allStates = iterator_to_array(SearchVariant::reindex_states());
        $this->assertEquals(
            array(
                array(
                    "SolrReindexTest_Variant" => "0"
                ),
                array(
                    "SolrReindexTest_Variant" => "1"
                ),
                array(
                    "SolrReindexTest_Variant" => "2"
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
        $this->createDummyData(120);

        // Initiate re-index
        $logger = new SolrReindexTest_RecordingLogger();
        $this->getHandler()->runReindex($logger, 21, 'Solr_Reindex');

        // Test that invalid classes are removed
        $this->assertNotEmpty($logger->getMessages('Clearing obsolete classes from SolrReindexTest_Index'));
        Phockito::verify($this->service, 1)
            ->deleteByQuery('-(ClassHierarchy:SolrReindexTest_Item)');

        // Test that valid classes in invalid variants are removed
        $this->assertNotEmpty($logger->getMessages(
            'Clearing all records of type SolrReindexTest_Item in the current state: {"SolrReindexTest_Variant":"2"}'
        ));
        Phockito::verify($this->service, 1)
            ->deleteByQuery('+(ClassHierarchy:SolrReindexTest_Item) +(_testvariant:"2")');

        // 120x2 grouped into groups of 21 results in 12 groups
        $this->assertEquals(12, $logger->countMessages('Called processGroup with '));
        $this->assertEquals(6, $logger->countMessages('{"SolrReindexTest_Variant":"0"}'));
        $this->assertEquals(6, $logger->countMessages('{"SolrReindexTest_Variant":"1"}'));

        // Given that there are two variants, there should be two group ids of each number
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 0 of 6'));
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 1 of 6'));
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 2 of 6'));
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 3 of 6'));
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 4 of 6'));
        $this->assertEquals(2, $logger->countMessages(' SolrReindexTest_Item, group 5 of 6'));

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
        $this->createDummyData(120);
        $logger = new SolrReindexTest_RecordingLogger();

        // Initiate re-index of third group (index 2 of 6)
        $state = array('SolrReindexTest_Variant' => '1');
        $this->getHandler()->runGroup($logger, $this->index, $state, 'SolrReindexTest_Item', 6, 2);
        $idMessage = $logger->filterMessages('Updated ');
        $this->assertNotEmpty(preg_match('/^Updated (?<ids>[,\d]+)/i', $idMessage[0], $matches));
        $ids = array_unique(explode(',', $matches['ids']));

        // Test successful
        $this->assertNotEmpty($logger->getMessages('Adding SolrReindexTest_Item'));
        $this->assertNotEmpty($logger->getMessages('Done'));

        // Test that items in this variant / group are cleared from solr
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=2 u=2}mod(ID, 6)" +(_testvariant:"1")'
        );

        // Test that items in this variant / group are re-indexed
        // 120 divided into 6 groups should be 20 at least (max 21)
        $this->assertEquals(21, count($ids), 'Group size is about 20', 1);
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
        $this->createDummyData(120);
        $logger = new SolrReindexTest_RecordingLogger();

        // Test that running all groups covers the complete set of ids
        $state = array('SolrReindexTest_Variant' => '1');
        for ($i = 0; $i < 6; $i++) {
            // See testReindexSegmentsGroups for test that each of these states is invoked during a full reindex
            $this
                ->getHandler()
                ->runGroup($logger, $this->index, $state, 'SolrReindexTest_Item', 6, $i);
        }

        // Count all ids updated
        $ids = array();
        foreach ($logger->filterMessages('Updated ') as $message) {
            $this->assertNotEmpty(preg_match('/^Updated (?<ids>[,\d]+)/', $message, $matches));
            $ids = array_unique(array_merge($ids, explode(',', $matches['ids'])));
        }

        // Check ids
        $this->assertEquals(120, count($ids));
        Phockito::verify($this->service, 6)->deleteByQuery(\Hamcrest_Matchers::anything());
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=0 u=0}mod(ID, 6)" +(_testvariant:"1")'
        );
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=1 u=1}mod(ID, 6)" +(_testvariant:"1")'
        );
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=2 u=2}mod(ID, 6)" +(_testvariant:"1")'
        );
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=3 u=3}mod(ID, 6)" +(_testvariant:"1")'
        );
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=4 u=4}mod(ID, 6)" +(_testvariant:"1")'
        );
        Phockito::verify($this->service, 1)->deleteByQuery(
            '+(ClassHierarchy:SolrReindexTest_Item) +_query_:"{!frange l=5 u=5}mod(ID, 6)" +(_testvariant:"1")'
        );
    }
}
