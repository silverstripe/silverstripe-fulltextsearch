<?php


class BatchedProcessorTest_Object extends SiteTree implements TestOnly
{
    private static $db = array(
        'TestText' => 'Varchar'
    );
}

class BatchedProcessorTest_Index extends SearchIndex_Recording implements TestOnly
{
    public function init()
    {
        $this->addClass('BatchedProcessorTest_Object');
        $this->addFilterField('TestText');
    }
}

class BatchedProcessor_QueuedJobService
{
    protected $jobs = array();

    public function queueJob(QueuedJob $job, $startAfter = null, $userId = null, $queueName = null)
    {
        $this->jobs[] = array(
            'job' => $job,
            'startAfter' => $startAfter
        );
        return $job;
    }

    public function getJobs()
    {
        return $this->jobs;
    }
}

/**
 * Tests {@see SearchUpdateQueuedJobProcessor}
 */
class BatchedProcessorTest extends SapphireTest
{
    protected $oldProcessor;

    protected $extraDataObjects = array(
        'BatchedProcessorTest_Object'
    );

    protected $illegalExtensions = array(
        'SiteTree' => array(
            'SiteTreeSubsites',
            'Translatable'
        )
    );

    public function setUpOnce()
    {
        // Disable illegal extensions if skipping this test
        if (class_exists('Subsite') || !interface_exists('QueuedJob')) {
            $this->illegalExtensions = array();
        }
        parent::setUpOnce();
    }

    public function setUp()
    {
        parent::setUp();

        if (!interface_exists('QueuedJob')) {
            $this->skipTest = true;
            $this->markTestSkipped("These tests need the QueuedJobs module installed to run");
        }

        if (class_exists('Subsite')) {
            $this->skipTest = true;
            $this->markTestSkipped(get_class() . ' skipped when running with subsites');
        }

        SS_Datetime::set_mock_now('2015-05-07 06:00:00');

        Config::inst()->update('SearchUpdateBatchedProcessor', 'batch_size', 5);
        Config::inst()->update('SearchUpdateBatchedProcessor', 'batch_soft_cap', 0);

        Versioned::reading_stage("Stage");

        Injector::inst()->registerService(new BatchedProcessor_QueuedJobService(), 'QueuedJobService');

        FullTextSearch::force_index_list('BatchedProcessorTest_Index');

        $this->oldProcessor = SearchUpdater::$processor;
        SearchUpdater::$processor = new SearchUpdateQueuedJobProcessor();
    }

    public function tearDown()
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
                'BatchedProcessorTest_Object',
                array(array(
                    'id' => $id,
                    'state' => array('SearchVariantVersioned' => 'Stage')
                )),
                'BatchedProcessorTest_Index'
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
        $index = singleton('BatchedProcessorTest_Index');
        $index->reset();
        $processor = $this->generateDirtyIds();

        // Check initial state
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->totalSteps);
        $this->assertEquals(0, $data->currentStep);
        $this->assertEmpty($data->isComplete);
        $this->assertEquals(0, count($index->getAdded()));

        // Advance state
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
            $data = $processor->getJobData();
            $this->assertEquals($pass, $data->currentStep);
            $this->assertEquals($pass * 5, count($index->getAdded()));
        }

        // Last run should have two hanging items
        $processor->process();
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded()));
        $this->assertTrue($data->isComplete);
    }

    /**
     * Tests that the batch_soft_cap setting is properly respected
     */
    public function testSoftCap()
    {
        $index = singleton('BatchedProcessorTest_Index');
        $index->reset();
        $processor = $this->generateDirtyIds();

        // Test that increasing the soft cap to 2 will reduce the number of batches
        Config::inst()->update('SearchUpdateBatchedProcessor', 'batch_soft_cap', 2);
        $processor->batchData();
        $data = $processor->getJobData();
        //Debug::dump($data);die;
        $this->assertEquals(8, $data->totalSteps);

        // A soft cap of 1 should not fit in the hanging two items
        Config::inst()->update('SearchUpdateBatchedProcessor', 'batch_soft_cap', 1);
        $processor->batchData();
        $data = $processor->getJobData();
        $this->assertEquals(9, $data->totalSteps);

        // Extra large soft cap should fit both items
        Config::inst()->update('SearchUpdateBatchedProcessor', 'batch_soft_cap', 4);
        $processor->batchData();
        $data = $processor->getJobData();
        $this->assertEquals(8, $data->totalSteps);

        // Process all data and ensure that all are processed adequately
        for ($pass = 1; $pass <= 8; $pass++) {
            $processor->process();
        }
        $data = $processor->getJobData();
        $this->assertEquals(8, $data->currentStep);
        $this->assertEquals(42, count($index->getAdded()));
        $this->assertTrue($data->isComplete);
    }
}
