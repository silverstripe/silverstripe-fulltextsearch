<?php

namespace SilverStripe\FullTextSearch\Search\Processors;

use SilverStripe\Core\Config\Configurable;

/**
 * Provides batching of search updates
 */
abstract class SearchUpdateBatchedProcessor extends SearchUpdateProcessor
{
    use Configurable;

    /**
     * List of batches to be processed
     *
     * @var array
     */
    protected $batches;

    /**
     * Pointer to index of $batches assigned to $current.
     * Set to 0 (first index) if not started, or count + 1 if completed.
     *
     * @var int
     */
    protected $currentBatch;

    /**
     * List of indexes successfully comitted in the current batch
     *
     * @var array
     */
    protected $completedIndexes;

    /**
     * Maximum number of record-states to process in one batch.
     * Set to zero to process all records in a single batch
     *
     * @config
     * @var int
     */
    private static $batch_size = 100;

    /**
     * Up to this number of additional ids can be added to any batch in order to reduce the number
     * of batches
     *
     * @config
     * @var int
     */
    private static $batch_soft_cap = 10;

    public function __construct()
    {
        parent::__construct();

        $this->batches = array();
        $this->setBatch(0);
    }

    /**
     * Set the current batch index
     *
     * @param int $batch Index of the batch
     */
    protected function setBatch($batch)
    {
        $this->currentBatch = $batch;
    }

    protected function getSource()
    {
        if (isset($this->batches[$this->currentBatch])) {
            return $this->batches[$this->currentBatch];
        }
    }

    /**
     * Process the current queue
     *
     * @return boolean
     */
    public function process()
    {
        // Skip blank queues
        if (empty($this->batches)) {
            return true;
        }

        // Don't re-process completed queue
        if ($this->currentBatch >= count($this->batches)) {
            return true;
        }

        // Send current patch to indexes
        $this->prepareIndexes();

        // Advance to next batch if successful
        $this->setBatch($this->currentBatch + 1);
        return true;
    }

    /**
     * Segments batches acording to the specified rules
     *
     * @param array $source Source input
     * @return array Batches
     */
    protected function segmentBatches($source)
    {
        // Measure batch_size
        $batchSize = static::config()->get('batch_size');
        if ($batchSize === 0) {
            return array($source);
        }
        $softCap = static::config()->get('batch_soft_cap');

        // Clear batches
        $batches = array();
        $current = array();
        $currentSize = 0;

        // Build batches from data
        foreach ($source as $base => $statefulids) {
            if (!$statefulids) {
                continue;
            }

            foreach ($statefulids as $stateKey => $statefulid) {
                $state = $statefulid['state'];
                $ids = $statefulid['ids'];
                if (!$ids) {
                    continue;
                }

                // Extract items from $ids until empty
                while ($ids) {
                    // Estimate maximum number of items to take for this iteration, allowing for the soft cap
                    $take = $batchSize - $currentSize;
                    if (count($ids) <= $take + $softCap) {
                        $take += $softCap;
                    }
                    $items = array_slice($ids, 0, $take, true);
                    $ids = array_slice($ids, count($items), null, true);

                    // Update batch
                    $currentSize += count($items);
                    $merge = array(
                        $base => array(
                            $stateKey => array(
                                'state' => $state,
                                'ids' => $items
                            )
                        )
                    );
                    $current = $current ? array_merge_recursive($current, $merge) : $merge;
                    if ($currentSize >= $batchSize) {
                        $batches[] = $current;
                        $current = array();
                        $currentSize = 0;
                    }
                }
            }
        }
        // Add incomplete batch
        if ($currentSize) {
            $batches[] = $current;
        }

        return $batches;
    }

    public function batchData()
    {
        $this->batches = $this->segmentBatches($this->dirty);
        $this->setBatch(0);
    }

    public function triggerProcessing()
    {
        $this->batchData();
    }
}
