<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexQueuedTest;

use SilverStripe\Dev\TestOnly;

if (!class_exists('SilverStripe\QueuedJobs\Services\QueuedJobService')) {
    return;
}

use SilverStripe\QueuedJobs\Services\QueuedJobService;

class SolrReindexQueuedTest_Service extends QueuedJobService implements TestOnly
{
    private static $dependencies = [
        'queueHandler' => '%$QueueHandler'
    ];

    /**
     * @return QueuedJob
     */
    public function getNextJob()
    {
        $job = $this->getNextPendingJob();
        return $this->initialiseJob($job);
    }
}
