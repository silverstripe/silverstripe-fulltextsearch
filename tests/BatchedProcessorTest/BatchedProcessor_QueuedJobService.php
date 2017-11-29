<?php

namespace SilverStripe\FullTextSearch\Tests\BatchedProcessorTest;

use Symbiote\QueuedJobs\Services\QueuedJob;

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
