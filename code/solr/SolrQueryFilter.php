<?php

/**
 * Provides caching and rate limiting optimisations to {@see SolrIndex}
 */
class SolrQueryFilter extends Extension {
	
	/**
	 * Time duration (in second) to allow for query execution. Search requests within this
	 * time period while another query is in progress will be presented with a 429 (rate limit)
	 * HTTP error. Once a search has completed execution then the timeout is reset to rate_cooldown
	 * (if one is set) or zero otherwise.
	 *
	 * @config
	 * @var int
	 */
	private static $rate_timeout = 10;
	
	/**
	 * Time duration (in sections) to deny further search requests after a successful search.
	 * Search requests within this time period while another query is in progress will be
	 * presented with a 429 (rate limit)
	 *
	 * @config
	 * @var int
	 */
	private static $rate_cooldown = 2;
	
	/**
	 * Determine if the rate limiting should be locked on a per-query basis.
	 *
	 * @config
	 * @var bool
	 */
	private static $rate_byquery = false;
	
	/**
	 * Determine if rate limiting should be applied independently to each IP address. This method is not
	 * always reliable protection against DDoS as most attacks use multiple IP addresses, but it does
	 * prevent different users from being rate limited by each other.
	 *
	 * @config
	 * @var bool
	 */
	private static $rate_byuserip = false;
	
	/**
	 * True if rate limiting should be enabled
	 * 
	 * @config
	 * @var bool
	 */
	private static $rate_enabled = true;
	
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
	 * Gets the cache to use
	 * 
	 * @return Zend_Cache_Frontend
	 */
	public function getFilterCache() {
		$cache = SS_Cache::factory('SolrQueryFilter');
		$cache->setOption('automatic_serialization', true);
		return $cache;
	}
	
	/**
	 * Determines the key to use for saving the current rate
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return string Result key
	 */
	protected function getRateLockKey(SolrService $service, $arguments) {
		// Distinguish this service by path
		$entropy = $service->getPath();
		
		// Identify search by search query
		if($this->owner->config()->rate_byquery) {
			$entropy .= serialize($arguments);
		}
		
		// Identify by user if configured
		if($this->owner->config()->rate_byuserip && Controller::has_curr()) {
			$entropy .= Controller::curr()->getRequest()->getIP();
		}
		
		return 'SolrQueryFilter_Rate_' .md5($entropy);
	}
	
	/**
	 * Determines the key to use for saving cached results for a query
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return string Result key
	 */
	protected function getCacheKey(SolrService $service, $arguments) {
		// Distinguish this service by path
		$entropy = $service->getPath();
		
		// Identify search by search query
		$entropy .= serialize($arguments);
		
		// Distinguish this service by path and query arguments
		return 'SolrQueryFilter_Cache_' . md5($entropy);
	}
	
	/**
	 * Hook called just prior to SolrService::query
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return Apache_Solr_Response A resulting cached query, if available, or null otherwise
	 * @throws SS_HTTPResponse_Exception
	 */
	public function onBeforeSearch(SolrService $service, $arguments) {
		// Check for cached result
		if($result = $this->getCachedResult($service, $arguments)) {
			return $result;
		}
		
		// Apply rate limiting rules
		$this->applyRateLimit($service, $arguments);
		return null;
	}
	
	/**
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @param Apache_Solr_Response $serviceResult Result of either call to SolrService::query, or
	 * the value of any cached result. This may be null if no results are available.
	 * @param array $extendedResult The list of results returned from extend('onBeforeSearch');
	 */
	public function onAfterSearch(SolrService $service, $arguments, $serviceResult = null, $extendedResult = null) {
		// We shouldn't further cache or rate limit the result of already cached results
		if($extendedResult) return;
		
		// Save cached result
		$this->setCachedResult($service, $arguments, $serviceResult);
			
		// Reset rate limit for this request if a non cached result was performed
		$this->resetRateLimit($service, $arguments);
	}
	
	/**
	 * Attempt to retrieve cached results for a query
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @return Apache_Solr_Response A resulting cached query, if available, or null otherwise
	 */
	protected function getCachedResult(SolrService $service, $arguments) {
		// Bypass caching if disabled
		if(!$this->owner->config()->cache_enabled) return;
		
		// Retrieve result
		$cache = $this->getFilterCache();
		$cacheKey = $this->getCacheKey($service, $arguments);
		return $cache->load($cacheKey);
	}
	
	/**
	 * Save cached results
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @param Apache_Solr_Response $serviceResult Result of either call to SolrService::query, or
	 * the value of any cached result. This may be null if no results are available.
	 */
	protected function setCachedResult(SolrService $service, $arguments, $serviceResult) {
		// Bypass caching if disabled
		if(!$this->owner->config()->cache_enabled || empty($serviceResult)) return;
		
		// Store result
		$cache = $this->getFilterCache();
		$cacheKey = $this->getCacheKey($service, $arguments);
		$cacheLifetime = $this->owner->config()->cache_lifetime;
		$cache->save($serviceResult, $cacheKey, array(), $cacheLifetime);
	}


	/**
	 * Applies rate limiting rules to this query
	 * 
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 * @throws SS_HTTPResponse_Exception
	 */
	protected function applyRateLimit(SolrService $service, $arguments) {
		// Bypass rate limiting if disabled
		if(!$this->owner->config()->rate_enabled) return;
		
		// Generate result with rate limiting enabled
		$limitKey = $this->getRateLockKey($service, $arguments);
		$cache = $this->getFilterCache();
		if($lockedUntil = $cache->load($limitKey)) {
			if(time() < $lockedUntil) {
				// Politely inform visitor of limit
				$response = new SS_HTTPResponse_Exception('Too Many Requests.', 429);
				$response->getResponse()->addHeader('Retry-After', 1 + $lockedUntil - time());
				throw $response;
			}
		}
		
		// Lock this query for $timeout seconds
		$timeout = $this->owner->config()->rate_timeout;
		$cache->save(time() + $timeout, $limitKey);
	}
	
	/**
	 * @param SolrService $service Source service to query
	 * @param array $arguments Arguments to be passed to SolrService::query
	 */
	protected function resetRateLimit(SolrService $service, $arguments) {
		// Bypass rate limiting if disabled
		if(!$this->owner->config()->rate_enabled) return;
		
		// After search is performed, reset lock timeout to an appropriate time
		$limitKey = $this->getRateLockKey($service, $arguments);
		$cache = $this->getFilterCache();
		
		if($cooldown = $this->owner->config()->rate_cooldown) {
			// Set cooldown on successful query execution
			$cache->save(time() + $cooldown, $limitKey);
		} else {
			// Without cooldown simply disable lock
			$cache->remove($limitKey);
		}
	}
}
