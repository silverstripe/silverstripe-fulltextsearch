<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use Symbiote\QueuedJobs\Services\QueuedJob;

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
     * Name of index to reindex
     *
     * @var string
     */
    protected $indexName;

    /**
     * Variant state that this group belongs to
     *
     * @var type
     */
    protected $state;

    /**
     * Single class name to index
     *
     * @var string
     */
    protected $class;

    /**
     * Total number of groups
     *
     * @var int
     */
    protected $groups;

    /**
     * Group index
     *
     * @var int
     */
    protected $group;

    public function __construct($indexName = null, $state = null, $class = null, $groups = null, $group = null)
    {
        parent::__construct();
        $this->indexName = $indexName;
        $this->state = $state;
        $this->class = $class;
        $this->groups = $groups;
        $this->group = $group;
    }

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
        $logger = $this->getLogger();
        if ($this->jobFinished()) {
            $logger->notice("reindex group already complete");
            return;
        }

        // Get instance of index
        $indexInstance = singleton($this->indexName);

        // Send back to processor
        $logger->info("Beginning reindex group");
        $this
            ->getHandler()
            ->runGroup($logger, $indexInstance, $this->state, $this->class, $this->groups, $this->group);
        $logger->info("Completed reindex group");
        $this->isComplete = true;
    }
}
