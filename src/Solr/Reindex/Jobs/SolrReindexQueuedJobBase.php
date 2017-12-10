<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\FullTextSearch\Utils\Logging\SearchLogFactory;
use stdClass;
use Symbiote\QueuedJobs\Services\QueuedJob;

if (!interface_exists(QueuedJob::class)) {
    return;
}

/**
 * Base class for jobs which perform re-index
 */
abstract class SolrReindexQueuedJobBase implements QueuedJob
{
    /**
     * Flag whether this job is done
     *
     * @var bool
     */
    protected $isComplete;

    /**
     * List of messages
     *
     * @var array
     */
    protected $messages;

    /**
     * Logger to use for this job
     *
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->isComplete = false;
        $this->messages = array();
    }

    /**
     * @return SearchLogFactory
     */
    protected function getLoggerFactory()
    {
        return Injector::inst()->get(SearchLogFactory::class);
    }

    /**
     * Gets a logger for this job
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }

        // Set logger for this job
        $this->logger = $this
            ->getLoggerFactory()
            ->getQueuedJobLogger($this);
        return $this->logger;
    }

    /**
     * Assign custom logger for this job
     *
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function getJobData()
    {
        $data = new stdClass();

        // Standard fields
        $data->totalSteps = 1;
        $data->currentStep = $this->isComplete ? 0 : 1;
        $data->isComplete = $this->isComplete;
        $data->messages = $this->messages;

        // Custom data
        $data->jobData = new stdClass();
        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        $this->isComplete = $isComplete;
        $this->messages = $messages;
    }

    /**
     * Get the reindex handler
     *
     * @return SolrReindexHandler
     */
    protected function getHandler()
    {
        return Injector::inst()->get(SolrReindexHandler::class);
    }

    public function jobFinished()
    {
        return $this->isComplete;
    }

    public function prepareForRestart()
    {
        // NOOP
    }

    public function setup()
    {
        // NOOP
    }

    public function afterComplete()
    {
        // NOOP
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function getSignature()
    {
        return sha1(get_class($this) . time() . mt_rand(0, 100000));
    }

    public function addMessage($message)
    {
        $this->messages[] = $message;
    }
}
