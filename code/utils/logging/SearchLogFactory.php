<?php

namespace SilverStripe\FullTextSearch\Utils\Logging;

use Psr\Log;

interface SearchLogFactory
{
    /**
     * Make a logger for a queuedjob
     *
     * @param QueuedJob $job
     * @return Log
     */
    public function getQueuedJobLogger($job);

    /**
     * Get an output logger with the given verbosity
     *
     * @param string $name
     * @param bool $verbose
     * @return Log
     */
    public function getOutputLogger($name, $verbose);
}
