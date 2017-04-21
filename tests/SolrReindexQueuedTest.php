<?php

use SilverStripe\Dev\SapphireTest;

/**
 * Additional tests of solr reindexing processes when run with queuedjobs
 */
class SolrReindexQueuedTest extends SapphireTest
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

        if (!interface_exists('QueuedJob')) {
            $this->skipTest = true;
            return $this->markTestSkipped("These tests need the QueuedJobs module installed to run");
        }

        // Set queued handler for reindex
        Config::inst()->update('Injector', 'SolrReindexHandler', array(
            'class' => 'SolrReindexQueuedHandler'
        ));
        Injector::inst()->registerService(new SolrReindexQueuedHandler(), 'SolrReindexHandler');

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
     * @return SolrReindexQueuedTest_Service
     */
    protected function getQueuedJobService()
    {
        return singleton('SolrReindexQueuedTest_Service');
    }

    /**
     * Test that reindex will generate a top top level queued job, and executing this will perform
     * the necessary initialisation of the grouped queued jobs
     */
    public function testReindexSegmentsGroups()
    {
        $this->createDummyData(18);

        // Create pre-existing jobs
        $this->getQueuedJobService()->queueJob(new SolrReindexQueuedJob());
        $this->getQueuedJobService()->queueJob(new SolrReindexGroupQueuedJob());
        $this->getQueuedJobService()->queueJob(new SolrReindexGroupQueuedJob());

        // Initiate re-index
        $logger = new SolrReindexTest_RecordingLogger();
        $this->getHandler()->triggerReindex($logger, 6, 'Solr_Reindex');

        // Old jobs should be cancelled
        $this->assertEquals(1, $logger->countMessages('Cancelled 1 re-index tasks and 2 re-index groups'));
        $this->assertEquals(1, $logger->countMessages('Queued Solr Reindex Job'));

        // Next job should be queue job
        $job = $this->getQueuedJobService()->getNextJob();
        $this->assertInstanceOf('SolrReindexQueuedJob', $job);
        $this->assertEquals(6, $job->getBatchSize());

        // Test that necessary items are created
        $logger->clear();
        $job->setLogger($logger);
        $job->process();

        // Deletes are performed in the main task prior to individual groups being processed
        // 18 records means 3 groups of 6 in each variant (6 total)
        Phockito::verify($this->service, 2)
            ->deleteByQuery(\Hamcrest_Matchers::anything());
        $this->assertEquals(1, $logger->countMessages('Beginning init of reindex'));
        $this->assertEquals(6, $logger->countMessages('Queued Solr Reindex Group '));
        $this->assertEquals(3, $logger->countMessages(' of SolrReindexTest_Item in {"SolrReindexTest_Variant":"0"}'));
        $this->assertEquals(3, $logger->countMessages(' of SolrReindexTest_Item in {"SolrReindexTest_Variant":"1"}'));
        $this->assertEquals(1, $logger->countMessages('Completed init of reindex'));

        
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
    }

    /**
     * Test index processing on individual groups
     */
    public function testRunGroup()
    {
        $this->createDummyData(18);

        // Just do what the SolrReindexQueuedJob would do to create each sub
        $logger = new SolrReindexTest_RecordingLogger();
        $this->getHandler()->runReindex($logger, 6, 'Solr_Reindex');

        // Assert jobs are created
        $this->assertEquals(6, $logger->countMessages('Queued Solr Reindex Group'));

        // Check next job is a group queued job
        $job = $this->getQueuedJobService()->getNextJob();
        $this->assertInstanceOf('SolrReindexGroupQueuedJob', $job);
        $this->assertEquals(
            'Solr Reindex Group (1/3) of SolrReindexTest_Item in {"SolrReindexTest_Variant":"0"}',
            $job->getTitle()
        );

        // Running this job performs the necessary reindex
        $logger->clear();
        $job->setLogger($logger);
        $job->process();

        // Check tasks completed (as per non-queuedjob version)
        $this->assertEquals(1, $logger->countMessages('Beginning reindex group'));
        $this->assertEquals(1, $logger->countMessages('Adding SolrReindexTest_Item'));
        $this->assertEquals(1, $logger->countMessages('Queuing commit on all changes'));
        $this->assertEquals(1, $logger->countMessages('Completed reindex group'));

        // Check IDs
        $idMessage = $logger->filterMessages('Updated ');
        $this->assertNotEmpty(preg_match('/^Updated (?<ids>[,\d]+)/i', $idMessage[0], $matches));
        $ids = array_unique(explode(',', $matches['ids']));
        $this->assertEquals(6, count($ids));
        foreach ($ids as $id) {
            // Each id should be % 3 == 0
            $this->assertEquals(0, $id % 3, "ID $id Should match pattern ID % 3 = 0");
        }
    }
}

if (!class_exists('QueuedJobService')) {
    return;
}

class SolrReindexQueuedTest_Service extends QueuedJobService implements TestOnly
{
    /**
     * @return QueuedJob
     */
    public function getNextJob()
    {
        $job = $this->getNextPendingJob();
        return $this->initialiseJob($job);
    }
}
