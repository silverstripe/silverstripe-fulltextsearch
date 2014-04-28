<?php

Solr::include_client_api();


/**
 * Interface to a SolrSearch instance
 */
interface SolrService_Engine {
	
	/**
	 * Add a Solr Document to the index
	 *
	 * @param Apache_Solr_Document $document
	 * @param boolean $allowDups
	 * @param boolean $overwritePending
	 * @param boolean $overwriteCommitted
	 * @param integer $commitWithin The number of milliseconds that a document must be committed within, see @{link http://wiki.apache.org/solr/UpdateXmlMessages#The_Update_Schema} for details.  If left empty this property will not be set in the request.
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 */
	public function addDocument(Apache_Solr_Document $document, $allowDups = false, $overwritePending = true, $overwriteCommitted = true, $commitWithin = 0);
	
	/**
	 * Create a delete document based on document ID
	 *
	 * @param string $id Expected to be utf-8 encoded
	 * @param boolean $fromPending
	 * @param boolean $fromCommitted
	 * @param float $timeout Maximum expected duration of the delete operation on the server (otherwise, will throw a communication exception)
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 */
	public function deleteById($id, $fromPending = true, $fromCommitted = true, $timeout = 3600);
	
	/**
	 * Send a commit command.  Will be synchronous unless both wait parameters are set to false.
	 *
	 * @param boolean $expungeDeletes Defaults to false, merge segments with deletes away
	 * @param boolean $waitFlush Defaults to true,  block until index changes are flushed to disk
	 * @param boolean $waitSearcher Defaults to true, block until a new searcher is opened and registered as the main query searcher, making the changes visible
	 * @param float $timeout Maximum expected duration (in seconds) of the commit operation on the server (otherwise, will throw a communication exception). Defaults to 1 hour
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 */
	public function commit($expungeDeletes = false, $waitFlush = true, $waitSearcher = true, $timeout = 3600);
	
	/**
	 * Simple Search interface
	 *
	 * @param string $query The raw query string
	 * @param int $offset The starting offset for result documents
	 * @param int $limit The maximum number of result documents to return
	 * @param array $params key / value pairs for other query parameters (see Solr documentation), use arrays for parameter keys used more than once (e.g. facet.field)
	 * @param string $method The HTTP method (Apache_Solr_Service::METHOD_GET or Apache_Solr_Service::METHOD::POST)
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 * @throws Apache_Solr_InvalidArgumentException If an invalid HTTP method is used
	 */
	public function search($query, $offset = 0, $limit = 10, $params = array(), $method = self::METHOD_GET);
	
	/**
	 * Get the set path.
	 *
	 * @return string
	 */
	public function getPath();
}

/**
 * Interface to a SolrService_Engine which has additional reporting capabilities
 */
interface SolrService_Engine_Reportable extends SolrService_Engine {
	
	/**
	 * Return an array of data describing the last search
	 * 
	 * @return array
	 */
	public function getReport();
}

/**
 * The API for accessing a specific core of a Solr server. Exactly the same as Apache_Solr_Service for now.
 */
class SolrService_Core extends Apache_Solr_Service implements SolrService_Engine {

}

/**
 * The API for accessing the primary Solr installation, which includes both SolrService_Core,
 * plus extra methods for interrogating, creating, reloading and getting SolrService_Core instances
 * for Solr cores.
 */
class SolrService extends SolrService_Core {
	private static $core_class = 'SolrService_Core';

	/**
	 * Handle encoding the GET parameters and making the HTTP call to execute a core command
	 */
	protected function coreCommand($command, $core, $params=array()) {
		$command = strtoupper($command);

		$params = array_merge($params, array('action' => $command, 'wt' => 'json'));
		$params[$command == 'CREATE' ? 'name' : 'core'] = $core;

		return $this->_sendRawGet($this->_constructUrl('admin/cores', $params));
	}

	/**
	 * Is the passed core active?
	 * @param $core string - The name of the core
	 * @return boolean - True if that core exists & is active
	 */
	public function coreIsActive($core) {
		$result = $this->coreCommand('STATUS', $core);
		return isset($result->status->$core->uptime);
	}

	/**
	 * Create a new core
	 * @param $core string - The name of the core
	 * @param $instancedir string - The base path of the core on the server
	 * @param $config string - The filename of solrconfig.xml on the server. Default is $instancedir/solrconfig.xml
	 * @param $schema string - The filename of schema.xml on the server. Default is $instancedir/schema.xml
	 * @param $datadir string - The path to store data for this core on the server. Default depends on solrconfig.xml
	 * @return Apache_Solr_Response
	 */
	public function coreCreate($core, $instancedir, $config=null, $schema=null, $datadir=null) {
		$args = array('instanceDir' => $instancedir);
		if ($config) $args['config'] = $config;
		if ($schema) $args['schema'] = $schema;
		if ($datadir) $args['dataDir'] = $datadir;

		return $this->coreCommand('CREATE', $core, $args);
	}

	/**
	 * Reload a core
	 * @param $core string - The name of the core
	 * @return Apache_Solr_Response
	 */
	public function coreReload($core) {
		return $this->coreCommand('RELOAD', $core);
	}

	/**
	 * Create a new Solr3Service_Core instance for the passed core
	 * @param $core string - The name of the core
	 * @return Solr3Service_Core
	 */
	public function serviceForCore($core) {
		$klass = Config::inst()->get(get_called_class(), 'core_class');
		return new $klass($this->_host, $this->_port, $this->_path.$core, $this->_httpTransport);
	}
}


/**
 * Provides caching and rate limiting optimisations to {@see SolrIndex}
 */
class SolrService_Cache implements SolrService_Engine_Reportable {
	
	/**
	 * Number of seconds to cache results for.
	 * 
	 * @config
	 * @var int
	 */
	private static $cache_lifetime = 300;
	
	/**
	 * True if caching should be enabled
	 * 
	 * @config
	 * @var bool
	 */
	private static $cache_enabled = true;
	
	/**
	 * Parent search service to cache
	 *
	 * @var SolrService_Engine
	 */
	protected $parent = null;
	
	/**
	 * Indicate whether the last attempt to call search was a cache hit
	 *
	 * @var bool
	 */
	protected $cacheHit = false;
	
	public function __construct(SolrService_Engine $parent) {
		$this->parent = $parent;
		$this->cacheHit = false;
	}
	
	/**
	 * Gets the cache to use
	 * 
	 * @return Zend_Cache_Frontend
	 */
	protected function getFilterCache() {
		$cache = SS_Cache::factory('SolrService_Cache');
		$cache->setOption('automatic_serialization', true);
		return $cache;
	}
	
	/**
	 * Discards cached data
	 */
	protected function invalidateCache() {
		$this
			->getFilterCache()
			->clean(Zend_Cache::CLEANING_MODE_ALL);
	}
	
	/**
	 * Determines the key to use for saving cached results for a query
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return string Result key
	 */
	protected function getCacheKey($arguments) {
		// Distinguish this service by path
		$entropy = $this->parent->getPath();
		
		// Identify search by search query
		$entropy .= serialize($arguments);
		
		// Distinguish this service by path and query arguments
		return 'SolrQueryFilter_Cache_' . md5($entropy);
	}
	
	public function search($query, $offset = 0, $limit = 10, $params = array(), $method = self::METHOD_GET) {
		$this->cacheHit = false;
		
		// Check for cached result
		$arguments = func_get_args();
		if($result = $this->getCachedResult($arguments)) {
			$this->cacheHit = true;
			return $result;
		}
		
		// Generate result
		$result = $this->parent->search($query, $offset, $limit, $params, $method);
		
		// Save cached result
		$this->setCachedResult($arguments, $result);
		return $result;
	}
	
	/**
	 * Attempt to retrieve cached results for a query
	 * 
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return Apache_Solr_Response A resulting cached query, if available, or null otherwise
	 */
	protected function getCachedResult($arguments) {
		// Bypass caching if disabled
		if(!Config::inst()->get(get_class(), 'cache_enabled')) return null;
		
		// Retrieve result
		$cache = $this->getFilterCache();
		$cacheKey = $this->getCacheKey($arguments);
		return $cache->load($cacheKey);
	}
	
	/**
	 * Save cached results
	 * 
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @param Apache_Solr_Response $results Result of either call to SolrService::query, or
	 * the value of any cached result. This may be null if no results are available.
	 */
	protected function setCachedResult($arguments, $results) {
		// Bypass caching if disabled
		if(!Config::inst()->get(get_class(), 'cache_enabled') || empty($results)) return;
		
		// Store result
		$cache = $this->getFilterCache();
		$cacheKey = $this->getCacheKey($arguments);
		$cacheLifetime = Config::inst()->get(get_class(), 'cache_lifetime');
		$cache->save($results, $cacheKey, array(), $cacheLifetime);
	}

	public function addDocument(\Apache_Solr_Document $document, $allowDups = false, $overwritePending = true, $overwriteCommitted = true, $commitWithin = 0) {
		$this->invalidateCache();
		return $this->parent->addDocument($document, $allowDups, $overwritePending, $overwriteCommitted, $commitWithin);
	}
	
	public function deleteById($id, $fromPending = true, $fromCommitted = true, $timeout = 3600) {
		$this->invalidateCache();
		return $this->parent->deleteById($id, $fromPending, $fromCommitted, $timeout);
	}
	
	public function commit($expungeDeletes = false, $waitFlush = true, $waitSearcher = true, $timeout = 3600) {
		$this->invalidateCache();
		return $this->parent->commit($expungeDeletes, $waitFlush, $waitSearcher, $timeout);
	}

	public function getPath() {
		return $this->parent->getPath();
	}
	
	public function getReport() {
		return array(
			'cachehit' => $this->cacheHit
		);
	}
}
