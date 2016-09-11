<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Solr
{
    /**
     * Configuration on where to find the solr server and how to get new index configurations into it.
     *
     * Required fields:
     * host (default: localhost) - The host or IP Solr is listening on
     * port (default: 8983) - The port Solr is listening on
     * path (default: /solr) - The suburl the solr service is available on
     *
     * Optional fields:
     * version (default: 4) - The Solr server version. Currently supports 3 and 4 (you can add a sub-version like 4.5 if
     *   you like, but currently it has no effect)
     * service (default: depends on version, Solr3Service for 3, Solr4Service for 4)
     *   the class that provides actual communcation to the Solr server
     * extraspath (default: <basefolder>/fulltextsearch/conf/solr/{version}/extras/) - Absolute path to
     *   the folder containing templates which are used for generating the schema and field definitions.
     * templates (default: <basefolder>/fulltextsearch/conf/solr/{version}/templates/) - Absolute path to
     *   the configuration default files, e.g. solrconfig.xml.
     *
     * indexstore => an array with
     *
     *   mode - a classname which implements SolrConfigStore, or 'file' or 'webdav'
     *
     *   When mode == SolrConfigStore_File or file (indexes should be written on a local filesystem)
     *      path - The (locally accessible) path to write the index configurations to.
     *      remotepath (default: the same as indexpath) - The path that the Solr server will read the index configurations from
     *
     *   When mode == SolrConfigStore_WebDAV or webdav (indexes should stored on a remote Solr server via webdav)
     *      auth (default: none) - A username:password pair string to use to auth against the webdav server
     *      path (default: /solrindex) - The suburl on the solr host that is set up to accept index configurations via webdav
     *      port (default: none) - The port for WebDAV if different from the Solr port
     *      remotepath - The path that the Solr server will read the index configurations from
     */
    protected static $solr_options = array();

    /** A cache of solr_options with the defaults all merged in */
    protected static $merged_solr_options = null;

    /**
     * Update the configuration for Solr. See $solr_options for a discussion of the accepted array keys
     * @param array $options - The options to update
     */
    public static function configure_server($options = array())
    {
        self::$solr_options = array_merge(self::$solr_options, $options);
        self::$merged_solr_options = null;

        self::$service_singleton = null;
        self::$service_core_singletons = array();
    }

    /**
     * Get the configured Solr options with the defaults all merged in
     * @return array - The merged options
     */
    public static function solr_options()
    {
        if (self::$merged_solr_options) {
            return self::$merged_solr_options;
        }

        $defaults = array(
            'host' => 'localhost',
            'port' => 8983,
            'path' => '/solr',
            'version' => '4'
        );

        // Build some by-version defaults
        $version = isset(self::$solr_options['version']) ? self::$solr_options['version'] : $defaults['version'];

        if (version_compare($version, '4', '>=')) {
            $versionDefaults = array(
                'service' => 'Solr4Service',
                'extraspath' => Director::baseFolder().'/fulltextsearch/conf/solr/4/extras/',
                'templatespath' => Director::baseFolder().'/fulltextsearch/conf/solr/4/templates/',
            );
        } else {
            $versionDefaults = array(
                'service' => 'Solr3Service',
                'extraspath' => Director::baseFolder().'/fulltextsearch/conf/solr/3/extras/',
                'templatespath' => Director::baseFolder().'/fulltextsearch/conf/solr/3/templates/',
            );
        }

        return (self::$merged_solr_options = array_merge($defaults, $versionDefaults, self::$solr_options));
    }


    public static function set_service_class($class)
    {
        user_error('set_service_class is deprecated - pass as part of $options to configure_server', E_USER_WARNING);
        self::configure_server(array('service' => $class));
    }

    /** @var SolrService | null - The instance of SolrService for core management */
    protected static $service_singleton = null;
    /** @var [SolrService_Core] - The instances of SolrService_Core for each core */
    protected static $service_core_singletons = array();

    /**
     * Get a SolrService
     *
     * @param string $core Optional name of index class
     * @return SolrService_Core
     */
    public static function service($core = null)
    {
        $options = self::solr_options();

        if (!self::$service_singleton) {
            self::$service_singleton = Object::create(
                $options['service'], $options['host'], $options['port'], $options['path']
            );
        }

        if ($core) {
            if (!isset(self::$service_core_singletons[$core])) {
                self::$service_core_singletons[$core] = self::$service_singleton->serviceForCore(
                    singleton($core)->getIndexName()
                );
            }

            return self::$service_core_singletons[$core];
        } else {
            return self::$service_singleton;
        }
    }

    public static function get_indexes()
    {
        return FullTextSearch::get_indexes('SolrIndex');
    }

    /**
     * Include the thirdparty Solr client api library. Done this way to avoid issues where code is called in
     * mysite/_config before fulltextsearch/_config has a change to update the include path.
     */
    public static function include_client_api()
    {
        static $included = false;

        if (!$included) {
            set_include_path(get_include_path() . PATH_SEPARATOR . Director::baseFolder() . '/fulltextsearch/thirdparty/solr-php-client');
            require_once('Apache/Solr/Service.php');
            require_once('Apache/Solr/Document.php');

            $included = true;
        }
    }
}

/**
 * Abstract class for build tasks
 */
class Solr_BuildTask extends BuildTask
{
    protected $enabled = false;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * Get the current logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Assign a new logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SearchLogFactory
     */
    protected function getLoggerFactory()
    {
        return Injector::inst()->get('SearchLogFactory');
    }

    /**
     * Setup task
     *
     * @param SS_HTTPReqest $request
     */
    public function run($request)
    {
        $name = get_class($this);
        $verbose = $request->getVar('verbose');

        // Set new logger
        $logger = $this
            ->getLoggerFactory()
            ->getOutputLogger($name, $verbose);
        $this->setLogger($logger);
    }
}


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
        $this->getLogger()->info("Configuring $index.");

        // Upload the config files for this index
        $this->getLogger()->info("Uploading configuration ...");
        $instance->uploadConfig($store);

        // Then tell Solr to use those config files
        $service = Solr::service();
        if ($service->coreIsActive($index)) {
            $this->getLogger()->info("Reloading core ...");
            $service->coreReload($index);
        } else {
            $this->getLogger()->info("Creating core ...");
            $service->coreCreate($index, $store->instanceDir($index));
        }

        $this->getLogger()->info("Done");
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
            user_error('No index configuration for Solr provided', E_USER_ERROR);
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

/**
 * Task used for both initiating a new reindex, as well as for processing incremental batches
 * within a reindex.
 *
 * When running a complete reindex you can provide any of the following
 *  - class (to limit to a single class)
 *  - verbose (optional)
 *
 * When running with a single batch, provide the following querystring arguments:
 *  - start
 *  - index
 *  - class
 *  - variantstate
 *  - verbose (optional)
 */
class Solr_Reindex extends Solr_BuildTask
{
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
        return Injector::inst()->get('SolrReindexHandler');
    }

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        parent::run($request);

        // Reset state
        $originalState = SearchVariant::current_state();
        $this->doReindex($request);
        SearchVariant::activate_state($originalState);
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
            foreach(ClassInfo::subclassesFor('SolrIndex') as $solrIndexClass) {
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

        // Deprecated reindex mechanism
        $start = $request->getVar('start');
        if ($start !== null) {
            // Run single batch directly
            $indexInstance = singleton($index);
            $state = json_decode($request->getVar('variantstate'), true);
            $this->runFrom($indexInstance, $class, $start, $state);
            return;
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
        $self = get_class($this);
        $handler->triggerReindex($this->getLogger(), $this->config()->recordsPerRequest, $self, $class);
    }

    /**
     * @deprecated since version 2.0.0
     */
    protected function runFrom($index, $class, $start, $variantstate)
    {
        DeprecationTest_Deprecation::notice('2.0.0', 'Solr_Reindex now uses a new grouping mechanism');

        // Set time limit and state
        increase_time_limit_to();
        SearchVariant::activate_state($variantstate);

        // Generate filtered list
        $items = DataList::create($class)
            ->limit($this->config()->recordsPerRequest, $start);

        // Add child filter
        $classes = $index->getClasses();
        $options = $classes[$class];
        if (!$options['include_children']) {
            $items = $items->filter('ClassName', $class);
        }

        // Process selected records in this class
        $this->getLogger()->info("Adding $class");
        foreach ($items->sort("ID") as $item) {
            $this->getLogger()->debug($item->ID);

            // See SearchUpdater_ObjectHandler::triggerReindex
            $item->triggerReindex();
            $item->destroy();
        }

        $this->getLogger()->info("Done");
    }
}
