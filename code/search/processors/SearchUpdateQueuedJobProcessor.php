<?php

if (!interface_exists('QueuedJob')) {
    return;
}

class SearchUpdateQueuedJobProcessor extends SearchUpdateBatchedProcessor implements QueuedJob
{
    /**
     * The QueuedJob queue to use when processing updates
     * @config
     * @var int
     */
    private static $reindex_queue = 2; // QueuedJob::QUEUED;

    protected $messages = array();

    public function triggerProcessing()
    {
        parent::triggerProcessing();
        singleton('QueuedJobService')->queueJob($this);
    }

    public function getTitle()
    {
        return "FullTextSearch Update Job";
    }

    public function getSignature()
    {
        return md5(get_class($this) . time() . mt_rand(0, 100000));
    }

    public function getJobType()
    {
        return Config::inst()->get('SearchUpdateQueuedJobProcessor', 'reindex_queue');
    }

    public function jobFinished()
    {
        return $this->currentBatch >= count($this->batches);
    }

    public function setup()
    {
        // NOP
    }

    public function prepareForRestart()
    {
        // NOP
    }

    public function afterComplete()
    {
        // NOP
    }

    public function getJobData()
    {
        $data = new stdClass();
        $data->totalSteps = count($this->batches);
        $data->currentStep = $this->currentBatch;
        $data->isComplete = $this->jobFinished();
        $data->messages = $this->messages;

        $data->jobData = new stdClass();
        $data->jobData->batches = $this->batches;
        $data->jobData->currentBatch = $this->currentBatch;

        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        $this->isComplete = $isComplete;
        $this->messages = $messages;

        $this->batches = $jobData->batches;
        $this->currentBatch = $jobData->currentBatch;
    }

    public function addMessage($message, $severity='INFO')
    {
        $severity = strtoupper($severity);
        $this->messages[] = '[' . date('Y-m-d H:i:s') . "][$severity] $message";
    }

    public function process()
    {
        $result = parent::process();

        if ($this->jobFinished()) {
            $this->addMessage("All batched updates complete.");
        }

        return $result;
    }
}
