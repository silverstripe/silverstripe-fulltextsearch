<?php

class Solr4Service_Core extends SolrService_Core {

	/**
	 * Replace underlying commit function to remove waitFlush in 4.0+, since it's been deprecated and 4.4 throws errors
	 * if you pass it
	 */
	public function commit($expungeDeletes = false, $waitFlush = null, $waitSearcher = true, $timeout = 3600) {
		if ($waitFlush) {
			user_error('waitFlush must be false when using Solr 4.0+' . E_USER_ERROR);
		}

		$expungeValue = $expungeDeletes ? 'true' : 'false';
		$searcherValue = $waitSearcher ? 'true' : 'false';

		$rawPost = '<commit expungeDeletes="' . $expungeValue . '" waitSearcher="' . $searcherValue . '" />';
		return $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);
	}
}

class Solr4Service extends SolrService {
	private static $core_class = 'Solr4Service_Core';
}

