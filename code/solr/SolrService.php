<?php

Solr::include_client_api();

class SolrService extends Apache_Solr_Service {

	/**
	 * @return Apache_Solr_Response
	 */
	protected function coreCommand($command, $core, $params=array()) {
		$command = strtoupper($command);

		$params = array_merge($params, array('action' => $command, 'wt' => 'json'));
		$params[$command == 'CREATE' ? 'name' : 'core'] = $core;

		return $this->_sendRawGet($this->_constructUrl('admin/cores', $params));
	}

	/**
	 * @return boolean
	 */
	public function coreIsActive($core) {
		$result = $this->coreCommand('STATUS', $core);
		return isset($result->status->$core->uptime);
	}

	/**
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
	 * @return Apache_Solr_Response
	 */
	public function coreReload($core) {
		return $this->coreCommand('RELOAD', $core);
	}

	protected $_serviceCache = array();

	public function serviceForCore($core) {
		if (!isset($this->_serviceCache[$core])) {
			$this->_serviceCache[$core] = new self($this->_host, $this->_port, $this->_path."$core", $this->_httpTransport);
		}

		return $this->_serviceCache[$core];
	}

	/**
	 * A version of commit() that won't send the waitFlush parameter, which is useless and removed in SOLR 4
	 */
	public function commit($expungeDeletes = false, $waitFlush = true, $waitSearcher = true, $timeout = 3600) {
		$expungeValue = $expungeDeletes ? 'true' : 'false';
		$searcherValue = $waitSearcher ? 'true' : 'false';
		$rawPost = '<commit expungeDeletes="' . $expungeValue . '" waitSearcher="' . $searcherValue . '" />';
		return $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);
	}
}
