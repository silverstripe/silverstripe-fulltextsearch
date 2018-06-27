<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Handlers;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Environment;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;

/**
 * Base class for re-indexing of solr content
 */
abstract class SolrReindexBase implements SolrReindexHandler
{
    public function runReindex(LoggerInterface $logger, $batchSize, $taskName, $classes = null)
    {
        foreach (Solr::get_indexes() as $indexInstance) {
            $this->processIndex($logger, $indexInstance, $batchSize, $taskName, $classes);
        }
    }

    /**
     * Process index for a single SolrIndex instance
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance
     * @param int $batchSize
     * @param string $taskName
     * @param string $classes
     */
    protected function processIndex(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $batchSize,
        $taskName,
        $classes = null
    ) {
        // Filter classes for this index
        $indexClasses = $this->getClassesForIndex($indexInstance, $classes);

        // Clear all records in this index which do not contain the given classes
        $logger->info("Clearing obsolete classes from " . $indexInstance->getIndexName());
        $indexInstance->clearObsoleteClasses($indexClasses);

        // Build queue for each class
        foreach ($indexClasses as $class => $options) {
            $includeSubclasses = $options['include_children'];

            foreach (SearchVariant::reindex_states($class, $includeSubclasses) as $state) {
                $this->processVariant($logger, $indexInstance, $state, $class, $includeSubclasses, $batchSize, $taskName);
            }
        }
    }

    /**
     * Get valid classes and options for an index with an optional filter
     *
     * @param SolrIndex $index
     * @param string|array $filterClasses Optional class or classes to limit to
     * @return array List of classes, where the key is the classname and value is list of options
     */
    protected function getClassesForIndex(SolrIndex $index, $filterClasses = null)
    {
        // Get base classes
        $classes = $index->getClasses();
        if (!$filterClasses) {
            return $classes;
        }

        // Apply filter
        if (!is_array($filterClasses)) {
            $filterClasses = explode(',', $filterClasses);
        }
        return array_intersect_key($classes, array_combine($filterClasses, $filterClasses));
    }

    /**
     * Process re-index for a given variant state and class
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance
     * @param array $state Variant state
     * @param string $class
     * @param bool $includeSubclasses
     * @param int $batchSize
     * @param string $taskName
     */
    protected function processVariant(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $includeSubclasses,
        $batchSize,
        $taskName
    ) {
        // Set state
        SearchVariant::activate_state($state);

        // Count records
        $query = $class::get();
        if (!$includeSubclasses) {
            $query = $query->filter('ClassName', $class);
        }
        $total = $query->count();

        // Skip this variant if nothing to process, or if there are no records
        if ($total == 0 || $indexInstance->variantStateExcluded($state)) {
            // Remove all records in the current state, since there are no groups to process
            $logger->info("Clearing all records of type {$class} in the current state: " . json_encode($state));
            $this->clearRecords($indexInstance, $class);
            return;
        }

        // For each group, run processing
        $groups = (int)(($total + $batchSize - 1) / $batchSize);
        for ($group = 0; $group < $groups; $group++) {
            $this->processGroup($logger, $indexInstance, $state, $class, $groups, $group, $taskName);
        }
    }

    /**
     * Initiate the processing of a single group
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance Index instance
     * @param array $state Variant state
     * @param string $class Class to index
     * @param int $groups Total groups
     * @param int $group Index of group to process
     * @param string $taskName Name of task script to run
     */
    abstract protected function processGroup(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group,
        $taskName
    );

    /**
     * Explicitly invoke the process that performs the group
     * processing. Can be run either by a background task or a queuedjob.
     *
     * Does not commit changes to the index, so this must be controlled externally.
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance
     * @param array $state
     * @param string $class
     * @param int $groups
     * @param int $group
     */
    public function runGroup(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group
    ) {
        // Set time limit and state
        Environment::increaseTimeLimitTo();
        SearchVariant::activate_state($state);
        $logger->info("Adding $class");

        // Prior to adding these records to solr, delete existing solr records
        $this->clearRecords($indexInstance, $class, $groups, $group);

        // Process selected records in this class
        $items = $this->getRecordsInGroup($indexInstance, $class, $groups, $group);
        $processed = array();
        foreach ($items as $item) {
            $processed[] = $item->ID;

            // By this point, obsolete classes/states have been removed in processVariant
            // and obsolete records have been removed in clearRecords
            $indexInstance->add($item);
            $item->destroy();
        }
        $logger->info("Updated " . implode(',', $processed));

        // This will slow down things a tiny bit, but it is done so that we don't timeout to the database during a reindex
        DB::query('SELECT 1');

        $logger->info("Done");
    }

    /**
     * Gets the datalist of records in the given group in the current state
     *
     * Assumes that the desired variant state is in effect.
     *
     * @param SolrIndex $indexInstance
     * @param string $class
     * @param int $groups
     * @param int $group
     * @return DataList
     */
    protected function getRecordsInGroup(SolrIndex $indexInstance, $class, $groups, $group)
    {
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $items = DataList::create($class)
            ->where(sprintf(
                '"%s"."ID" %% \'%d\' = \'%d\'',
                DataObject::getSchema()->tableName($baseClass),
                intval($groups),
                intval($group)
            ))
            ->sort("ID");

        // Add child filter
        $classes = $indexInstance->getClasses();
        $options = $classes[$class];
        if (!$options['include_children']) {
            $items = $items->filter('ClassName', $class);
        }

        return $items;
    }

    /**
     * Clear all records of the given class in the current state ONLY.
     *
     * Optionally delete from a given group (where the group is defined as the ID % total groups)
     *
     * @param SolrIndex $indexInstance Index instance
     * @param string $class Class name
     * @param int $groups Number of groups, if clearing from a striped group
     * @param int $group Group number, if clearing from a striped group
     */
    protected function clearRecords(SolrIndex $indexInstance, $class, $groups = null, $group = null)
    {
        // Clear by classname
        $conditions = array("+(ClassHierarchy:{$class})");

        // If grouping, delete from this group only
        if ($groups) {
            $conditions[] = "+_query_:\"{!frange l={$group} u={$group}}mod(ID, {$groups})\"";
        }

        // Also filter by state (suffix on document ID)
        $query = new SearchQuery();
        SearchVariant::with($class)
            ->call('alterQuery', $query, $indexInstance);
        if ($query->isfiltered()) {
            $conditions = array_merge($conditions, $indexInstance->getFiltersComponent($query));
        }

        // Invoke delete on index
        $deleteQuery = implode(' ', $conditions);
        $indexInstance
            ->getService()
            ->deleteByQuery($deleteQuery);
    }
}
