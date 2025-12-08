<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use Override;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symfony\Component\Console\Output\BufferedOutput;

if (!interface_exists(QueuedJob::class)) {
    return;
}

/**
 * Represents a queuedjob which invokes a reindex
 */
class SolrReindexQueuedJob extends SolrReindexQueuedJobBase
{
    /**
     * @param int $batchSize
     * @param string $taskName
     * @param mixed[]|string $classes
     */
    public function __construct(/**
     * Size of each batch to run
     */
    protected $batchSize = null, /**
     * Name of devtask Which invoked this
     * Not necessary for re-index processing performed entirely by queuedjobs
     */
    protected $taskName = null, /**
     * List of classes to filter
     */
    protected $classes = null)
    {
        parent::__construct();
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
        $buffer = new BufferedOutput();
        $logger = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);

        if ($this->jobFinished()) {
            $logger->writeln("reindex already complete");
            return;
        }

        // Send back to processor
        $logger->writeln("Beginning init of reindex");
        $this
            ->getHandler()
            ->runReindex($logger, $this->batchSize, $this->taskName, $this->classes);
        $logger->writeln("Completed init of reindex");
        $this->addMessage($buffer->fetch());
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
