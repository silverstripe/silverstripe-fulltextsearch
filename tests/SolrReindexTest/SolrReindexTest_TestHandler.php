<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexBase;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * Provides a wrapper for testing SolrReindexBase
 */
class SolrReindexTest_TestHandler extends SolrReindexBase
{
    public function processGroup(
        PolyOutput $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group,
        $taskName
    ) {
        $indexName = $indexInstance->getIndexName();
        $stateName = json_encode($state);
        $logger->writeln("Called processGroup with {$indexName}, {$stateName}, {$class}, group {$group} of {$groups}");
    }

    public function triggerReindex(PolyOutput $logger, $batchSize, $taskName, $classes = null)
    {
        $logger->writeln("Called triggerReindex");
    }
}
