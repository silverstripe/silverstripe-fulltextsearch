<?php

namespace SilverStripe\FullTextSearch\Tests\BatchedProcessorTest;

use Symbiote\QueuedJobs\Services\QueuedJob;

class BatchedProcessor_QueuedJobService
{
    protected $jobs = [];

    public function queueJob(QueuedJob $job, $startAfter = null, $userId = null, $queueName = null)
    {
        $this->jobs[] = [
            'job' => $job,
            'startAfter' => $startAfter
        ];
        return $job;
    }

    public function getJobs()
    {
        return $this->jobs;
    }
}
