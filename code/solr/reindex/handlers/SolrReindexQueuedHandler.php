<?php

use Psr\Log\LoggerInterface;

if (!interface_exists('QueuedJob')) {
    return;
}

/**
 * Represents a queued task to start the reindex job
 */
class SolrReindexQueuedHandler extends SolrReindexBase
{
    /**
     * @return QueuedJobService
     */
    protected function getQueuedJobService()
    {
        return singleton('QueuedJobService');
    }

    /**
     * Cancel any cancellable jobs
     *
     * @param string $type Type of job to cancel
     * @return int Number of jobs cleared
     */
    protected function cancelExistingJobs($type)
    {
        $clearable = array(
            // Paused jobs need to be discarded
            QueuedJob::STATUS_PAUSED,
            
            // These types would be automatically started
            QueuedJob::STATUS_NEW,
            QueuedJob::STATUS_WAIT,

            // Cancel any in-progress job
            QueuedJob::STATUS_INIT,
            QueuedJob::STATUS_RUN
        );
        DB::query(sprintf(
            'UPDATE "QueuedJobDescriptor" '
                . ' SET "JobStatus" = \'%s\''
                . ' WHERE "JobStatus" IN (\'%s\')'
                . ' AND "Implementation" = \'%s\'',
            Convert::raw2sql(QueuedJob::STATUS_CANCELLED),
            implode("','", Convert::raw2sql($clearable)),
            Convert::raw2sql($type)
        ));
        return DB::affectedRows();
    }

    public function triggerReindex(LoggerInterface $logger, $batchSize, $taskName, $classes = null)
    {
        // Cancel existing jobs
        $queues = $this->cancelExistingJobs('SolrReindexQueuedJob');
        $groups = $this->cancelExistingJobs('SolrReindexGroupQueuedJob');
        $logger->info("Cancelled {$queues} re-index tasks and {$groups} re-index groups");

        // Although this class is used as a service (singleton) it may also be instantiated
        // as a queuedjob
        $job = Injector::inst()->create('SolrReindexQueuedJob', $batchSize, $taskName, $classes);
        $this
            ->getQueuedJobService()
            ->queueJob($job);

        $title = $job->getTitle();
        $logger->info("Queued {$title}");
    }

    protected function processGroup(
        LoggerInterface $logger, SolrIndex $indexInstance, $state, $class, $groups, $group, $taskName
    ) {
        // Trigger another job for this group
        $job = Injector::inst()->create(
            'SolrReindexGroupQueuedJob',
            get_class($indexInstance), $state, $class, $groups, $group
        );
        $this
            ->getQueuedJobService()
            ->queueJob($job);
        
        $title = $job->getTitle();
        $logger->info("Queued {$title}");
    }

    public function runGroup(
        LoggerInterface $logger, SolrIndex $indexInstance, $state, $class, $groups, $group
    ) {
        parent::runGroup($logger, $indexInstance, $state, $class, $groups, $group);

        // After any changes have been made, mark all indexes as dirty for commit
        // see http://stackoverflow.com/questions/7512945/how-to-fix-exceeded-limit-of-maxwarmingsearchers
        $logger->info("Queuing commit on all changes");
        SearchUpdateCommitJobProcessor::queue();
    }
}
