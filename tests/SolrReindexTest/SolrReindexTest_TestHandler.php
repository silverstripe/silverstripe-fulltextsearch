<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexBase;
use Psr\Log\LoggerInterface;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

/**
 * Provides a wrapper for testing SolrReindexBase
 */
class SolrReindexTest_TestHandler extends SolrReindexBase
{
    public function processGroup(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group,
        $taskName
    ) {
        $indexName = $indexInstance->getIndexName();
        $stateName = json_encode($state);
        $logger->info("Called processGroup with {$indexName}, {$stateName}, {$class}, group {$group} of {$groups}");
    }

    public function triggerReindex(LoggerInterface $logger, $batchSize, $taskName, $classes = null)
    {
        $logger->info("Called triggerReindex");
    }
}
