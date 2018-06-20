<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexQueuedTest;

use SilverStripe\Dev\TestOnly;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (!class_exists(QueuedJobService::class)) {
    return;
}

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
