<?php

namespace SilverStripe\FullTextSearch\Solr\Tasks;

use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\ORM\DataList;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

/**
 * Task used for both initiating a new reindex, as well as for processing incremental batches
 * within a reindex.
 *
 * When running a complete reindex you can provide any of the following
 *  - class (to limit to a single class)
 *  - verbose (optional)
 *
 * When running with a single batch, provide the following querystring arguments:
 *  - index
 *  - class
 *  - variantstate
 *  - verbose (optional)
 */
class Solr_Reindex extends Solr_BuildTask
{
    private static $segment = 'Solr_Reindex';

    protected $enabled = true;

    /**
     * Number of records to load and index per request
     *
     * @var int
     * @config
     */
    private static $recordsPerRequest = 200;

    /**
     * Get the reindex handler
     *
     * @return SolrReindexHandler
     */
    protected function getHandler()
    {
        return Injector::inst()->get(SolrReindexHandler::class);
    }

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        parent::run($request);

        $this->extend('updateBeforeSolrReindexTask', $request);

        // Reset state
        $originalState = SearchVariant::current_state();
        $this->doReindex($request);
        SearchVariant::activate_state($originalState);

        $this->extend('updateAfterSolrReindexTask', $request);
    }

    /**
     * @param SS_HTTPRequest $request
     */
    protected function doReindex($request)
    {
        $class = $request->getVar('class');

        $index = $request->getVar('index');

        //find the index classname by IndexName
        // this is for when index names do not match the class name (this can be done by overloading getIndexName() on
        // indexes
        if ($index && !ClassInfo::exists($index)) {
            foreach (ClassInfo::subclassesFor(SolrIndex::class) as $solrIndexClass) {
                $reflection = new ReflectionClass($solrIndexClass);
                //skip over abstract classes
                if (!$reflection->isInstantiable()) {
                    continue;
                }
                //check the indexname matches the index passed to the request
                if (!strcasecmp(singleton($solrIndexClass)->getIndexName(), $index)) {
                    //if we match, set the correct index name and move on
                    $index = $solrIndexClass;
                    break;
                }
            }
        }

        // Check if we are re-indexing a single group
        // If not using queuedjobs, we need to invoke Solr_Reindex as a separate process
        // Otherwise each group is processed via a SolrReindexGroupJob
        $groups = $request->getVar('groups');

        $handler = $this->getHandler();
        if ($groups) {
            // Run grouped batches (id % groups = group)
            $group = $request->getVar('group');
            $indexInstance = singleton($index);
            $state = json_decode($request->getVar('variantstate'), true);

            $handler->runGroup($this->getLogger(), $indexInstance, $state, $class, $groups, $group);
            return;
        }

        // If run at the top level, delegate to appropriate handler
        $taskName = $this->config()->segment ?: get_class($this);
        $handler->triggerReindex($this->getLogger(), $this->config()->recordsPerRequest, $taskName, $class);
    }
}
