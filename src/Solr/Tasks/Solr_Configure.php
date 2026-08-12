<?php
namespace SilverStripe\FullTextSearch\Solr\Tasks;

use Override;
use SilverStripe\PolyExecution\PolyOutput;
use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_File;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_Post;
use SilverStripe\FullTextSearch\Solr\Stores\SolrConfigStore_WebDAV;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class Solr_Configure extends Solr_BuildTask
{

    /**
     * @config
     */
    private static bool $is_enabled = true;

    protected string $title = 'Solr Configure';

    protected static string $description = 'Configure Solr Configuration';

    protected static string $commandName = 'solr-configure';

    #[Override]
    public function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->output = $output;
        $this->verbose = (bool) $input->getOption('verbose');

        $this->extend('updateBeforeSolrConfigureTask', $input, $output);

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $store = $this->getSolrConfigStore();

        $indexes = Solr::get_indexes();
        foreach ($indexes as $instance) {
            try {
                $this->updateIndex($instance, $store);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                // Always shown, regardless of verbosity - a failure is not a progress message.
                $this->info('<error>Failure: ' . $e->getMessage() . '</error>', false);
            }
        }

        if (isset($e)) {
            return Command::FAILURE;
        }

        $this->extend('updateAfterSolrConfigureTask', $input, $output);

        return Command::SUCCESS;
    }

    /**
     * Update the index on the given store
     *
     * @param SolrIndex $instance Instance
     * @param SolrConfigStore $store
     */
    protected function updateIndex($instance, $store)
    {
        $index = $instance->getIndexName();
        $this->info("Configuring $index.");

        // Upload the config files for this index
        $this->info("Uploading configuration ...");
        $instance->uploadConfig($store);

        // Then tell Solr to use those config files
        $service = Solr::service();
        if ($service->coreIsActive($index)) {
            $this->info("Reloading core ...");
            $service->coreReload($index);
        } else {
            $this->info("Creating core ...");
            $service->coreCreate($index, $store->instanceDir($index));
        }

        $this->info("Done");
    }

    /**
     * Get config store
     *
     * @return SolrConfigStore
     * @throws Exception
     */
    protected function getSolrConfigStore()
    {
        $options = Solr::solr_options();

        if (!isset($options['indexstore']) || !($indexstore = $options['indexstore'])) {
            throw new Exception('No index configuration for Solr provided', E_USER_ERROR);
        }

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $mode = $indexstore['mode'];

        if ($mode === 'file') {
            return new SolrConfigStore_File($indexstore);
        }
        if ($mode === 'webdav') {
            return new SolrConfigStore_WebDAV($indexstore);
        }
        if ($mode === 'post') {
            return new SolrConfigStore_Post($indexstore);
        }
        if (ClassInfo::exists($mode) && ClassInfo::classImplements($mode, SolrConfigStore::class)) {
            return new $mode($indexstore);
        }
        user_error('Unknown Solr index mode ' . $indexstore['mode'], E_USER_ERROR);
    }
}
