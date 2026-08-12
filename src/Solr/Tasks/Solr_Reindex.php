<?php

namespace SilverStripe\FullTextSearch\Solr\Tasks;

use Override;
use SilverStripe\PolyExecution\PolyOutput;
use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexHandler;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;

/**
 * Task used for both initiating a new reindex, as well as for processing incremental batches
 * within a reindex.
 *
 * When running a complete reindex you can provide any of the following
 *  - class (to limit to a single class)
 *  - verbose (optional)
 *
 * When running with a single batch, provide the following options:
 *  - index
 *  - class
 *  - groups
 *  - group
 *  - variantstate
 *  - verbose (optional)
 */
class Solr_Reindex extends Solr_BuildTask
{
    /**
     * @config
     */
    private static bool $is_enabled = true;

    protected string $title = 'Solr Reindex';

    protected static string $description = 'Reindex search indexes';

    protected static string $commandName = 'solr-reindex';

    /**
     * Number of records to load and index per request
     *
     * @var int
     * @config
     */
    private static int $recordsPerRequest = 200;

    /**
     * Get the reindex handler
     *
     * @return SolrReindexHandler
     */
    protected function getHandler()
    {
        return Injector::inst()->get(SolrReindexHandler::class);
    }

    #[Override]
    public function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->output = $output;
        $this->verbose = (bool) $input->getOption('verbose');

        $this->extend('updateBeforeSolrReindexTask', $input, $output);

        // Reset state
        $originalState = SearchVariant::current_state();
        $this->doReindex($input);
        SearchVariant::activate_state($originalState);

        $this->extend('updateAfterSolrReindexTask', $input, $output);

        return Command::SUCCESS;
    }

    protected function doReindex(InputInterface $input)
    {
        $class = $input->getOption('class');

        $index = $input->getOption('index');

        // find the index classname by IndexName
        // for when index names don't match the class name (this can be done by overloading getIndexName() on indexes
        if ($index && !ClassInfo::exists($index)) {
            foreach (ClassInfo::subclassesFor(SolrIndex::class) as $solrIndexClass) {
                $reflection = new ReflectionClass($solrIndexClass);

                //skip over abstract classes
                if (!$reflection->isInstantiable()) {
                    continue;
                }

                //check the indexname matches the index passed to the request
                if (!strcasecmp(singleton($solrIndexClass)->getIndexName() ?? '', $index ?? '')) {
                    //if we match, set the correct index name and move on
                    $index = $solrIndexClass;
                    break;
                }
            }
        }

        // Check if we are re-indexing a single group
        // If not using queuedjobs, we need to invoke Solr_Reindex as a separate process
        // Otherwise each group is processed via a SolrReindexGroupJob
        $groups = $input->getOption('groups');

        $handler = $this->getHandler();

        if ($groups) {
            // Run grouped batches (id % groups = group)
            $group = $input->getOption('group');
            $indexInstance = singleton($index);
            $state = json_decode($input->getOption('variantstate') ?? '', true);

            $handler->runGroup($this->output, $indexInstance, $state, $class, $groups, $group);
            return;
        }

        // If run at the top level, delegate to appropriate handler
        $handler->triggerReindex($this->output, $this->config()->recordsPerRequest, self::class, $class);
    }

    public function getOptions(): array
    {
        return [
            new InputOption('class', null, InputOption::VALUE_REQUIRED, 'Re-index specific class'),
            new InputOption('index', null, InputOption::VALUE_REQUIRED, 'Reindex specific index'),
            new InputOption('groups', null, InputOption::VALUE_REQUIRED, 'Total number of groups to segment the reindex into'),
            new InputOption('group', null, InputOption::VALUE_REQUIRED, 'Index of the group to process'),
            new InputOption('variantstate', null, InputOption::VALUE_REQUIRED, 'JSON encoded variant state to reindex in'),
        ];
    }
}
