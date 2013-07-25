<?php


class SearchUpdateQueuedJobProcessor extends SearchUpdateProcessor implements QueuedJob {

	/**
	 * The QueuedJob queue to use when processing updates
	 * @config
	 * @var string
	 */
	private static $reindex_queue = 2; // QueuedJob::QUEUED;

	protected $messages = array();
	protected $totalSteps = 0;
	protected $currentStep = 0;
	protected $isComplete = false;

	public function triggerProcessing() {
		singleton('QueuedJobService')->queueJob($this);
	}

	public function getTitle() {
		return "FullTextSearch Update Job";
	}

	public function getSignature() {
		return md5(get_class($this) . time() . mt_rand(0, 100000));
	}

	public function getJobType() {
		return Config::inst()->get('SearchUpdateQueuedJobProcessor', 'reindex_queue');
	}

	public function jobFinished() {
		return $this->isComplete;
	}

	public function setup() {
		$this->totalSteps = count(array_keys($this->dirty));
	}

	public function prepareForRestart() {
		// NOP
	}

	public function afterComplete() {
		// NOP
	}

	public function process() {
		if (parent::process() === false) {
			$this->currentStep += 1;
			$this->totalSteps += 1;
		}
		else {
			$this->currentStep = $this->totalSteps;
			$this->isComplete = true;
		}
	}

	public function getJobData() {
		$data = new stdClass();
		$data->totalSteps = $this->totalSteps;
		$data->currentStep = $this->currentStep;
		$data->isComplete = $this->isComplete;
		$data->messages = $this->messages;

		$data->jobData = new stdClass();
		$data->jobData->dirty = $this->dirty;
		$data->jobData->dirtyindexes = $this->dirtyindexes;

		return $data;
	}

	public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages) {
		$this->totalSteps = $totalSteps;
		$this->currentStep = $currentStep;
		$this->isComplete = $isComplete;
		$this->messages = $messages;

		$this->dirty = $jobData->dirty;
		$this->dirtyindexes = $jobData->dirtyindexes;
	}

	public function addMessage($message, $severity='INFO') {
		$severity = strtoupper($severity);
		$this->messages[] = '[' . date('Y-m-d H:i:s') . "][$severity] $message";
	}
}
