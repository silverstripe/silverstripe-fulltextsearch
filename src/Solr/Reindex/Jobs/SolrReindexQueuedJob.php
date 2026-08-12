<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use Override;
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
     * Name of devtask which invoked this
     * Not necessary for re-index processing performed entirely by queuedjobs
     *
     * @var string
     */
    protected $taskName;

    /**
     * List of classes to filter
     *
     * @var mixed[]|string
     */
    protected $classes;

    /**
     * @param int $batchSize
     * @param string $taskName
     * @param mixed[]|string $classes
     */
    public function __construct($batchSize = null, $taskName = null, $classes = null)
    {
        parent::__construct();

        $this->batchSize = $batchSize;
        $this->taskName = $taskName;
        $this->classes = $classes;
    }

    #[Override]
    public function getJobData()
    {
        $data = parent::getJobData();

        // Custom data
        $data->jobData->batchSize = $this->batchSize;
        $data->jobData->taskName = $this->taskName;
        $data->jobData->classes = $this->classes;

        return $data;
    }

    #[Override]
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
            $logger->writeln("reindex already complete");
            $this->flushBufferedOutput();
            return;
        }

        // Send back to processor
        $logger->writeln("Beginning init of reindex");
        $this
            ->getHandler()
            ->runReindex($logger, $this->batchSize, $this->taskName, $this->classes);
        $logger->writeln("Completed init of reindex");
        $this->flushBufferedOutput();
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
