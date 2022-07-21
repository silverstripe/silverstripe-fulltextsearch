<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessor_QueuedJobService;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessorTest_Index;
use SilverStripe\FullTextSearch\Tests\BatchedProcessorTest\BatchedProcessorTest_Object;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateCommitJobProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateQueuedJobProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateBatchedProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Subsites\Extensions\SiteTreeSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Tests {@see SearchUpdateQueuedJobProcessor}
 */
class BatchedProcessorTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected $oldProcessor;

    protected static $extra_dataobjects = [
        BatchedProcessorTest_Object::class,
    ];

    protected static $illegal_extensions = [
        SiteTree::class => [
            SiteTreeSubsites::class,
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        // Disable illegal extensions if skipping this test
        if (class_exists(Subsite::class) || !interface_exists(QueuedJob::class)) {
            static::$illegal_extensions = [];
        }
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(QueuedJob::class)) {
            $this->markTestSkipped("These tests need the QueuedJobs module installed to run");
        }

        if (class_exists(Subsite::class)) {
            $this->markTestSkipped(get_class() . ' skipped when running with subsites');
        }

        DBDatetime::set_mock_now('2015-05-07 06:00:00');

        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_size', 5);
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 0);
        Config::modify()->set(SearchUpdateCommitJobProcessor::class, 'cooldown', 600);

        Versioned::set_stage(Versioned::DRAFT);

        Injector::inst()->registerService(new BatchedProcessor_QueuedJobService(), QueuedJobService::class);

        FullTextSearch::force_index_list(BatchedProcessorTest_Index::class);

        SearchUpdateCommitJobProcessor::$dirty_indexes = array();
        SearchUpdateCommitJobProcessor::$has_run = false;

        $this->oldProcessor = SearchUpdater::$processor;
        SearchUpdater::$processor = new SearchUpdateQueuedJobProcessor();
    }

    protected function tearDown(): void
    {
        if ($this->oldProcessor) {
            SearchUpdater::$processor = $this->oldProcessor;
        }
        FullTextSearch::force_index_list();
        parent::tearDown();
    }

    /**
     * @return SearchUpdateQueuedJobProcessor
     */
    protected function generateDirtyIds()
    {
        $processor = SearchUpdater::$processor;
        for ($id = 1; $id <= 42; $id++) {
            // Save to db
            $object = new BatchedProcessorTest_Object();
            $object->TestText = 'Object ' . $id;
            $object->write();
            // Add to index manually
            $processor->addDirtyIDs(
                BatchedProcessorTest_Object::class,
                array(array(
                    'id' => $object->ID,
                    'state' => array(SearchVariantVersioned::class => 'Stage')
                )),
                BatchedProcessorTest_Index::class
            );
        }
        $processor->batchData();
        return $processor;
    }

    /**
     * Tests that large jobs are broken up into a suitable number of batches
     */
    public function testBatching()
    {
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', [SiteTree::class]);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();
        $processor = $this->generateDirtyIds();

        // Check initial state
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->totalSteps);
        $this->assertEquals(0, $data->currentStep);
        $this->assertEmpty($data->isComplete);
        $this->assertEquals(0, count($index->getAdded() ?? []));

        // Advance state
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
            $data = $processor->getJobData();
            $this->assertEquals($pass, $data->currentStep);
            $this->assertEquals($pass * 5, count($index->getAdded() ?? []));
        }

        // Last run should have two hanging items
        $processor->process();
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded() ?? []));
        $this->assertTrue($data->isComplete);

        // Check any additional queued jobs
        $processor->afterComplete();
        $service = singleton(QueuedJobService::class);
        $jobs = $service->getJobs();
        $this->assertEquals(1, count($jobs ?? []));
        $this->assertInstanceOf(SearchUpdateCommitJobProcessor::class, $jobs[0]['job']);
    }

    /**
     * Test creation of multiple commit jobs
     */
    public function testMultipleCommits()
    {
        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();

        // Test that running a commit immediately after submitting to the indexes
        // correctly commits
        $first = SearchUpdateCommitJobProcessor::queue();
        $second = SearchUpdateCommitJobProcessor::queue();

        $this->assertFalse($index->getIsCommitted());

        // First process will cause the commit
        $this->assertFalse($first->jobFinished());
        $first->process();
        $allMessages = $first->getMessages();
        $this->assertTrue($index->getIsCommitted());
        $this->assertTrue($first->jobFinished());
        $this->assertStringEndsWith('All indexes committed', $allMessages[2]);

        // Executing the subsequent processor should not re-trigger a commit
        $index->reset();
        $this->assertFalse($second->jobFinished());
        $second->process();
        $allMessages = $second->getMessages();
        $this->assertFalse($index->getIsCommitted());
        $this->assertTrue($second->jobFinished());
        $this->assertStringEndsWith('Indexing already completed this request: Discarding this job', $allMessages[0]);

        // Given that a third job is created, and the indexes are dirtied, attempting to run this job
        // should result in a delay
        $index->reset();
        $third = SearchUpdateCommitJobProcessor::queue();
        $this->assertFalse($third->jobFinished());
        $third->process();
        $this->assertTrue($third->jobFinished());
        $allMessages = $third->getMessages();
        $this->assertStringEndsWith(
            'Indexing already run this request, but incomplete. Re-scheduling for 2015-05-07 06:10:00',
            $allMessages[0]
        );
    }

    /**
     * Tests that the batch_soft_cap setting is properly respected
     */
    public function testSoftCap()
    {
        $this->markTestIncomplete(
            '@todo PostgreSQL: This test passes in isolation, but not in conjunction with the previous test'
        );

        $index = singleton(BatchedProcessorTest_Index::class);
        $index->reset();

        $processor = $this->generateDirtyIds();

        // Test that increasing the soft cap to 2 will reduce the number of batches
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 2);
        $processor->batchData();
        $data = $processor->getJobData();
        $this->assertEquals(8, $data->totalSteps);

        // A soft cap of 1 should not fit in the hanging two items
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 1);
        $processor->batchData();
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->totalSteps);

        // Extra large soft cap should fit both items
        Config::modify()->set(SearchUpdateBatchedProcessor::class, 'batch_soft_cap', 4);
        $processor->batchData();
        $data = $processor->getJobData();
        $this->assertEquals(8, $data->totalSteps);

        // Process all data and ensure that all are processed adequately
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
        }
        $data = $processor->getJobData();
        $this->assertEquals(8, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded() ?? []));
        $this->assertTrue($data->isComplete);
    }
}
