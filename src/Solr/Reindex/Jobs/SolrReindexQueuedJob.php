<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use Symbiote\QueuedJobs\Services\QueuedJob;

if (!interface_exists(QueuedJob::class)) {
    return;
}

/**
 * Represents a queuedjob which invokes a reindex
 */
class SolrReindexQueuedJob extends SolrReindexQueuedJobBase
{
    /**
     * Size of each batch to run
     *
     * @var int
     */
    protected $batchSize;

    /**
     * Name of devtask Which invoked this
     * Not necessary for re-index processing performed entirely by queuedjobs
     *
     * @var string
     */
    protected $taskName;

    /**
     * List of classes to filter
     *
     * @var array|string
     */
    protected $classes;

    public function __construct($batchSize = null, $taskName = null, $classes = null)
    {
        $this->batchSize = $batchSize;
        $this->taskName = $taskName;
        $this->classes = $classes;
        parent::__construct();
    }

    public function getJobData()
    {
        $data = parent::getJobData();

        // Custom data
        $data->jobData->batchSize = $this->batchSize;
        $data->jobData->taskName = $this->taskName;
        $data->jobData->classes = $this->classes;

        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        parent::setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages);

        // Custom data
        $this->batchSize = $jobData->batchSize;
        $this->taskName = $jobData->taskName;
        $this->classes = $jobData->classes;
    }

    public function getTitle()
    {
        return 'Solr Reindex Job';
    }

    public function process()
    {
        $logger = $this->getLogger();
        if ($this->jobFinished()) {
            $logger->notice("reindex already complete");
            return;
        }

        // Send back to processor
        $logger->info("Beginning init of reindex");
        $this
            ->getHandler()
            ->runReindex($logger, $this->batchSize, $this->taskName, $this->classes);
        $logger->info("Completed init of reindex");
        $this->isComplete = true;
    }

    /**
     * Get size of batch
     *
     * @return int
     */
    public function getBatchSize()
    {
        return $this->batchSize;
    }
}
