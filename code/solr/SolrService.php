<?php

Solr::include_client_api();

class SolrService extends Apache_Solr_Service {

	protected function coreCommand($command, $core, $params=array()) {
		$command = strtoupper($command);

		$params = array_merge($params, array('action' => $command, 'wt' => 'json'));
		$params[$command == 'CREATE' ? 'name' : 'core'] = $core;

		return $this->_sendRawGet($this->_constructUrl('admin/cores', $params));
	}

	public function coreIsActive($core) {
		$result = $this->coreCommand('STATUS', $core);
		return isset($result->status->$core->uptime);
	}

	public function coreCreate($core, $instancedir, $config=null, $schema=null, $datadir=null) {
		$args = array('instanceDir' => $instancedir);
		if ($config) $args['config'] = $config;
		if ($schema) $args['schema'] = $schema;
		if ($datadir) $args['dataDir'] = $datadir;

		$this->coreCommand('CREATE', $core, $args);
	}

	public function coreReload($core) {
		$this->coreCommand('RELOAD', $core);
	}

	protected $_serviceCache = array();

	public function serviceForCore($core) {
		if (!isset($this->_serviceCache[$core])) {
			$this->_serviceCache[$core] = new Apache_Solr_Service($this->_host, $this->_port, $this->_path."$core", $this->_httpTransport);
		}

		return $this->_serviceCache[$core];
	}
}
