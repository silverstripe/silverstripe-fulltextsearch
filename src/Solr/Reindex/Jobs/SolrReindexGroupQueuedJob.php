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
 * Queuedjob to re-index a small group within an index.
 *
 * This job is optimised for efficient full re-indexing of an index via Solr_Reindex.
 *
 * Operates similarly to {@see SearchUpdateQueuedJobProcessor} but can not work with an arbitrary
 * list of IDs. Instead groups are segmented by ID. Additionally, this task does incremental
 * deletions of records.
 */
class SolrReindexGroupQueuedJob extends SolrReindexQueuedJobBase
{
    /**
     * Variant state that this group belongs to
     *
     * @var type
     */
    protected $state;

    /**
     * @param string $indexName
     * @param string $class
     * @param int $groups
     * @param int $group
     */
    public function __construct(/**
     * Name of index to reindex
     */
    protected $indexName = null, $state = null, /**
     * Single class name to index
     */
    protected $class = null, /**
     * Total number of groups
     */
    protected $groups = null, /**
     * Group index
     */
    protected $group = null)
    {
        parent::__construct();
        $this->state = $state;
    }

    #[Override]
    public function getJobData()
    {
        $data = parent::getJobData();

        // Custom data
        $data->jobData->indexName = $this->indexName;
        $data->jobData->state = $this->state;
        $data->jobData->class = $this->class;
        $data->jobData->groups = $this->groups;
        $data->jobData->group = $this->group;

        return $data;
    }

    #[Override]
    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        parent::setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages);

        // Custom data
        $this->indexName = $jobData->indexName;
        $this->state = $jobData->state;
        $this->class = $jobData->class;
        $this->groups = $jobData->groups;
        $this->group = $jobData->group;
    }

    public function getTitle()
    {
        return sprintf(
            'Solr Reindex Group (%d/%d) of %s in %s',
            ($this->group+1),
            $this->groups,
            $this->class,
            json_encode($this->state)
        );
    }

    public function process()
    {
        $buffer = new BufferedOutput();
        $logger = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);

        if ($this->jobFinished()) {
            $logger->writeln("reindex group already complete");
            return;
        }

        // Get instance of index
        $indexInstance = singleton($this->indexName);

        // Send back to processor
        $logger->writeln("Beginning reindex group");
        $this
            ->getHandler()
            ->runGroup($logger, $indexInstance, $this->state, $this->class, $this->groups, $this->group);
        $logger->writeln("Completed reindex group");
        $this->addMessage($buffer->fetch());
        $this->isComplete = true;
    }
}
