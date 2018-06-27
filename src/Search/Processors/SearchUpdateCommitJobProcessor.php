<?php

namespace SilverStripe\FullTextSearch\Search\Processors;

use DateTime;
use DateInterval;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use stdClass;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (!interface_exists(QueuedJob::class)) {
    return;
}

class SearchUpdateCommitJobProcessor implements QueuedJob
{
    /**
     * The QueuedJob queue to use when processing commits
     *
     * @config
     * @var string
     */
    private static $commit_queue = QueuedJob::QUEUED;

    /**
     * List of indexes to commit
     *
     * @var array
     */
    protected $indexes = array();

    /**
     * True if this job is skipped to be be re-scheduled in the future
     *
     * @var boolean
     */
    protected $skipped = false;

    /**
     * List of completed indexes
     *
     * @var array
     */
    protected $completed = array();

    /**
     * List of messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * List of dirty indexes to be committed
     *
     * @var array
     */
    public static $dirty_indexes = array();

    /**
     * If solrindex::commit has already been performed, but additional commits are necessary,
     * how long do we wait before attempting to touch the index again?
     *
     * {@see http://stackoverflow.com/questions/7512945/how-to-fix-exceeded-limit-of-maxwarmingsearchers}
     *
     * @var int
     * @config
     */
    private static $cooldown = 300;

    /**
     * True if any commits have been executed this request. If so, any attempts to run subsequent commits
     * should be delayed until next queuedjob to prevent solr reaching maxWarmingSearchers
     *
     * {@see http://stackoverflow.com/questions/7512945/how-to-fix-exceeded-limit-of-maxwarmingsearchers}
     *
     * @var boolean
     */
    public static $has_run = false;

    /**
     * This method is invoked once indexes with dirty ids have been updapted and a commit is necessary
     *
     * @param boolean $dirty Marks all indexes as dirty by default. Set to false if there are known comitted and
     * clean indexes
     * @param string $startAfter Start date
     * @return int The ID of the next queuedjob to run. This could be a new one or an existing one.
     */
    public static function queue($dirty = true, $startAfter = null)
    {
        $commit = Injector::inst()->create(__CLASS__);
        $id = singleton(QueuedJobService::class)->queueJob($commit, $startAfter);

        if ($dirty) {
            $indexes = FullTextSearch::get_indexes();
            static::$dirty_indexes = array_keys($indexes);
        }
        return $id;
    }

    public function getJobType()
    {
        return Config::inst()->get(__CLASS__, 'commit_queue');
    }

    public function getSignature()
    {
        return sha1(get_class($this) . time() . mt_rand(0, 100000));
    }

    public function getTitle()
    {
        return "FullTextSearch Commit Job";
    }

    /**
     * Get the list of index names we should process
     *
     * @return array
     */
    public function getAllIndexes()
    {
        if (empty($this->indexes)) {
            $indexes = FullTextSearch::get_indexes();
            $this->indexes = array_keys($indexes);
        }
        return $this->indexes;
    }

    public function jobFinished()
    {
        // If we've indexed exactly as many as we would like, we are done
        return $this->skipped
            || (count($this->getAllIndexes()) <= count($this->completed));
    }

    public function prepareForRestart()
    {
        // NOOP
    }

    public function afterComplete()
    {
        // NOOP
    }

    /**
     * Abort this job, potentially rescheduling a replacement if there is still work to do
     */
    protected function discardJob()
    {
        $this->skipped = true;

        // If we do not have dirty records, then assume that these dirty records were committed
        // already this request (but probably another job), so we don't need to commit anything else.
        // This could occur if we completed multiple searchupdate jobs in a prior request, and
        // we only need one commit job to commit all of them in the current request.
        if (empty(static::$dirty_indexes)) {
            $this->addMessage("Indexing already completed this request: Discarding this job");
            return;
        }


        // If any commit has run, but some (or all) indexes are un-comitted, we must re-schedule this task.
        // This could occur if we completed a searchupdate job in a prior request, as well as in
        // the current request
        $cooldown = Config::inst()->get(__CLASS__, 'cooldown');
        $now = new DateTime(DBDatetime::now()->getValue());
        $now->add(new DateInterval('PT' . $cooldown . 'S'));
        $runat = $now->Format('Y-m-d H:i:s');

        $this->addMessage("Indexing already run this request, but incomplete. Re-scheduling for {$runat}");

        // Queue after the given cooldown
        static::queue(false, $runat);
    }

    public function process()
    {
        // If we have already run an instance of SearchUpdateCommitJobProcessor this request, immediately
        // quit this job to prevent hitting warming search limits in Solr
        if (static::$has_run) {
            $this->discardJob();
            return true;
        }

        // To prevent other commit jobs from running this request
        static::$has_run = true;

        // Run all incompleted indexes
        $indexNames = $this->getAllIndexes();
        foreach ($indexNames as $name) {
            $index = singleton($name);
            $this->commitIndex($index);
        }

        $this->addMessage("All indexes committed");

        return true;
    }

    /**
     * Commits a specific index
     *
     * @param SolrIndex $index
     * @throws Exception
     */
    protected function commitIndex($index)
    {
        // Skip index if this is already complete
        $name = get_class($index);
        if (in_array($name, $this->completed)) {
            $this->addMessage("Skipping already comitted index {$name}");
            return;
        }

        // Bypass SolrIndex::commit exception handling so that queuedjobs can handle the error
        $this->addMessage("Committing index {$name}");
        $index->getService()->commit(false, false, false);
        $this->addMessage("Committing index {$name} was successful");

        // If this index is currently marked as dirty, it's now clean
        if (in_array($name, static::$dirty_indexes)) {
            static::$dirty_indexes = array_diff(static::$dirty_indexes, array($name));
        }

        // Mark complete
        $this->completed[] = $name;
    }

    public function setup()
    {
        // NOOP
    }

    public function getJobData()
    {
        $data = new stdClass();
        $data->totalSteps = count($this->getAllIndexes());
        $data->currentStep = count($this->completed);
        $data->isComplete = $this->jobFinished();
        $data->messages = $this->messages;

        $data->jobData = new stdClass();
        $data->jobData->skipped = $this->skipped;
        $data->jobData->completed = $this->completed;
        $data->jobData->indexes = $this->getAllIndexes();

        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        $this->isComplete = $isComplete;
        $this->messages = $messages;

        $this->skipped = $jobData->skipped;
        $this->completed = $jobData->completed;
        $this->indexes = $jobData->indexes;
    }

    public function addMessage($message, $severity = 'INFO')
    {
        $severity = strtoupper($severity);
        $this->messages[] = '[' . date('Y-m-d H:i:s') . "][$severity] $message";
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
