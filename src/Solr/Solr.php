<?php

namespace SilverStripe\FullTextSearch\Solr;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Solr\Services\Solr4Service;
use SilverStripe\FullTextSearch\Solr\Services\Solr3Service;

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

        /** @var Module $module */
        $module = ModuleLoader::getModule('silverstripe/fulltextsearch');
        $modulePath = $module->getPath();

        if (version_compare($version, '4', '>=')) {
            $versionDefaults = [
                'service'       => Solr4Service::class,
                'extraspath'    => $modulePath . '/conf/solr/4/extras/',
                'templatespath' => $modulePath . '/conf/solr/4/templates/',
            ];
        } else {
            $versionDefaults = [
                'service'       => Solr3Service::class,
                'extraspath'    => $modulePath . '/conf/solr/3/extras/',
                'templatespath' => $modulePath . '/conf/solr/3/templates/',
            ];
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
            self::$service_singleton = Injector::inst()->create(
                $options['service'],
                $options['host'],
                $options['port'],
                $options['path']
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
        return FullTextSearch::get_indexes(SolrIndex::class);
    }
}
