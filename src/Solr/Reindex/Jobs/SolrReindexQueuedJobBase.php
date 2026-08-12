<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Jobs;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\PolyExecution\PolyOutput;
use stdClass;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
     * Output to write progress of this job to
     */
    protected ?PolyOutput $logger = null;

    /**
     * Buffer backing the default logger, so that output can be captured as job messages.
     * Null when a logger has been assigned externally via setLogger().
     */
    private ?BufferedOutput $buffer = null;

    public function __construct()
    {
        $this->isComplete = false;
        $this->messages = [];
    }

    /**
     * Gets the output to log progress to. Defaults to a buffered output which is
     * flushed into the job messages by flushBufferedOutput().
     */
    protected function getLogger(): PolyOutput
    {
        if (!$this->logger) {
            $this->buffer = new BufferedOutput();
            $this->logger = PolyOutput::create(
                PolyOutput::FORMAT_ANSI,
                OutputInterface::VERBOSITY_NORMAL,
                false,
                $this->buffer
            );
        }

        return $this->logger;
    }

    /**
     * Assign custom output for this job
     */
    public function setLogger(PolyOutput $logger): void
    {
        $this->logger = $logger;
        $this->buffer = null;
    }

    /**
     * Store anything written to the default buffered output as a job message
     */
    protected function flushBufferedOutput(): void
    {
        $output = $this->buffer?->fetch();
        if ($output) {
            $this->addMessage($output);
        }
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
        return sha1(static::class . time() . mt_rand(0, 100000));
    }

    public function addMessage($message)
    {
        $this->messages[] = $message;
    }
}
