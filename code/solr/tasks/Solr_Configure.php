<?php
namespace SilverStripe\FullTextSearch\Solr\Tasks;
class Solr_Configure extends Solr_BuildTask
{
    protected $enabled = true;

    public function run($request)
    {
        parent::run($request);

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $store = $this->getSolrConfigStore();

        $indexes = Solr::get_indexes();
        foreach ($indexes as $instance) {
            try {
                $this->updateIndex($instance, $store);
            } catch (Exception $e) {
                // We got an exception. Warn, but continue to next index.
                $this
                    ->getLogger()
                    ->error("Failure: " . $e->getMessage());
            }
        }
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
        $this->getLogger()->addInfo("Configuring $index.");

        // Upload the config files for this index
        $this->getLogger()->addInfo("Uploading configuration ...");
        $instance->uploadConfig($store);

        // Then tell Solr to use those config files
        $service = Solr::service();
        if ($service->coreIsActive($index)) {
            $this->getLogger()->addInfo("Reloading core ...");
            $service->coreReload($index);
        } else {
            $this->getLogger()->addInfo("Creating core ...");
            $service->coreCreate($index, $store->instanceDir($index));
        }

        $this->getLogger()->addInfo("Done");
    }

    /**
     * Get config store
     *
     * @return SolrConfigStore
     */
    protected function getSolrConfigStore()
    {
        $options = Solr::solr_options();

        if (!isset($options['indexstore']) || !($indexstore = $options['indexstore'])) {
            throw new Exception('No index configuration for Solr provided', E_USER_ERROR);
        }

        // Find the IndexStore handler, which will handle uploading config files to Solr
        $mode = $indexstore['mode'];

        if ($mode == 'file') {
            return new SolrConfigStore_File($indexstore);
        } elseif ($mode == 'webdav') {
            return new SolrConfigStore_WebDAV($indexstore);
        } elseif (ClassInfo::exists($mode) && ClassInfo::classImplements($mode, 'SolrConfigStore')) {
            return new $mode($indexstore);
        } else {
            user_error('Unknown Solr index mode '.$indexstore['mode'], E_USER_ERROR);
        }
    }
}