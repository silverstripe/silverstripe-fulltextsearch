<?php

namespace SilverStripe\FullTextSearch\Utils\Logging;

use Psr\Log;
use Symbiote\QueuedJobs\Services\QueuedJob;

interface SearchLogFactory
{
    /**
     * Make a logger for a queuedjob
     *
     * @param QueuedJob $job
     * @return Log\LoggerInterface
     */
    public function getQueuedJobLogger($job);

    /**
     * Get an output logger with the given verbosity
     *
     * @param string $name
     * @param bool $verbose
     * @return Log\LoggerInterface
     */
    public function getOutputLogger($name, $verbose);
}
