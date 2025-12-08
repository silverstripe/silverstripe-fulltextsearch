<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Handlers;

use Psr\Log\LoggerInterface;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * Provides interface for queueing a solr reindex
 */
interface SolrReindexHandler
{
    /**
     * Trigger a solr-reindex
     *
     * @param LoggerInterface $logger
     * @param int $batchSize Records to run each process
     * @param string $taskName Name of devtask to run
     * @param string|array|null $classes Optional class or classes to limit index to
     */
    public function triggerReindex(PolyOutput $logger, $batchSize, $taskName, $classes = null);

    /**
     * Begin an immediate re-index
     *
     * @param LoggerInterface $logger
     * @param int $batchSize Records to run each process
     * @param string $taskName Name of devtask to run
     * @param string|array|null $classes Optional class or classes to limit index to
     */
    public function runReindex(PolyOutput $logger, $batchSize, $taskName, $classes = null);

    /**
     * Do an immediate re-index on the given group, where the group is defined as the list of items
     * where ID mod $groups = $group, in the given $state and optional $class filter.
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance
     * @param array $state
     * @param string $class
     * @param int $groups
     * @param int $group
     */
    public function runGroup(PolyOutput $logger, SolrIndex $indexInstance, $state, $class, $groups, $group);
}
