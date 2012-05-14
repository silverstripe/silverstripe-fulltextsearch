<?php

class Solr  {

	/**
	 * Configuration on where to find the solr server and how to get new index configurations into it.
	 *
	 * Required fields:
	 * host (default: localhost) - The host or IP Solr is listening on
	 * port (default: 8983) - The port Solr is listening on
	 * path (default: /solr) - The suburl the solr service is available on
	 *
	 * indexstore => an array with
	 *
	 *   mode - 'file' or 'webdav'
	 *
	 *   When mode == file (indexes should be written on a local filesystem)
	 *      path - The (locally accessible) path to write the index configurations to.
	 *      remotepath (default: the same as indexpath) - The path that the Solr server will read the index configurations from
	 *
	 *   When mode == webdav (indexes should stored on a remote Solr server via webdav)
	 *      auth (default: none) - A username:password pair string to use to auth against the webdav server
	 *      path (default: /solrindex) - The suburl on the solr host that is set up to accept index configurations via webdav
	 *      remotepath - The path that the Solr server will read the index configurations from
	 */
	static $solr_options = array();

	static function configure_server($options = array()) {
		self::$solr_options = array_merge(array(
			'host' => 'localhost',
			'port' => 8983,
			'path' => '/solr'
		), self::$solr_options, $options);
	}

	static protected $service_class = 'SolrService';

	static function set_service_class($class) {
		self::$service_class = $class;
		self::$service = null;
	}

	static protected $service = null;

	static function service($core = null) {
		if (!self::$service) {
			if (!self::$solr_options) user_error('No configuration for Solr server provided', E_USER_ERROR);

			$class = self::$service_class;
			self::$service = new $class(self::$solr_options['host'], self::$solr_options['port'], self::$solr_options['path']);
		}

		return $core ? self::$service->serviceForCore($core) : self::$service;
	}

	static function get_indexes() {
		return FullTextSearch::get_indexes('SolrIndex');
	}

	/**
	 * Include the thirdparty Solr client api library. Done this way to avoid issues where code is called in mysite/_config
	 * before solr/_config has a change to update the include path.
	 */
	static function include_client_api() {
		static $included = false;

		if (!$included) {
			set_include_path(get_include_path() . PATH_SEPARATOR . Director::baseFolder() . '/solr/thirdparty/solr-php-client');
			require_once('Apache/Solr/Service.php');
			require_once('Apache/Solr/Document.php');

			$included = true;
		}
	}

}

class Solr_Configure extends BuildTask {

	public function run($request) {
		$service = Solr::service();

		if (!isset(Solr::$solr_options['indexstore']) || !($index = Solr::$solr_options['indexstore'])) {
			user_error('No index configuration for Solr provided', E_USER_ERROR);
		}

		$remote = null;

		switch ($index['mode']) {
			case 'file':
				$local = $index['path'];
				$remote = isset($index['remotepath']) ? $index['remotepath'] : $local;

				foreach (Solr::get_indexes() as $index => $instance) {
					$confdir = "$local/$index/conf";
					if (!is_dir($confdir)) mkdir($confdir, 0770, true);

					file_put_contents("$confdir/schema.xml", $instance->generateSchema());

					foreach (glob(Director::baseFolder().'/solr/conf/extras/*') as $file) {
						if (is_file($file)) copy($file, $confdir.'/'.basename($file));
					}
				}

				break;

			case 'webdav':
				$url = implode('', array(
					'http://',
					isset($index['auth']) ? $index['auth'].'@' : '',
					Solr::$solr_options['host'] . ':' . Solr::$solr_options['port'],
					$index['path']
				));

				$remote = $index['remotepath'];

				foreach (Solr::get_indexes() as $index => $instance) {
					$indexdir = "$url/$index";
					if (!WebDAV::exists($indexdir)) WebDAV::mkdir($indexdir);

					$confdir = "$url/$index/conf";
					if (!WebDAV::exists($confdir)) WebDAV::mkdir($confdir);

					WebDAV::upload_from_string($instance->generateSchema(), "$confdir/schema.xml");

					foreach (glob(Director::baseFolder().'/solr/conf/extras/*') as $file) {
						if (is_file($file)) WebDAV::upload_from_file($file, $confdir.'/'.basename($file));
					}
				}

				break;

			default:
				user_error('Unknown Solr index mode '.$index['mode'], E_USER_ERROR);
		}

		if ($service->coreIsActive($index)) $service->coreReload($index);
		else $service->coreCreate($index, "$remote/$index");
	}
}

class Solr_Reindex extends BuildTask {
	static $recordsPerRequest = 200;

	public function run($request) {
		increase_time_limit_to();
		$self = get_class($this);

		$originalState = SearchVariant::current_state();

		if (isset($_GET['start'])) {
			$variantstate = array_values(json_decode($_GET['variantstate'],true));
			$this->runFrom(singleton($_GET['index']), $_GET['class'], $_GET['start'], $variantstate[0]);
		}
		else {
			foreach(array('framework','sapphire') as $dirname) {
				$script = sprintf("%s%s$dirname%scli-script.php", BASE_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
				if(file_exists($script)) {
					break;
				}
			}
			$class = get_class($this);

			foreach (Solr::get_indexes() as $index => $instance) {
				echo "Rebuilding {$instance->getIndexName()}\n\n";

				Solr::service($index)->deleteByQuery('*:*');

				foreach ($instance->getClasses() as $class => $options) {
					$includeSubclasses = $options['include_children'];

					foreach (SearchVariant::reindex_states($class, $includeSubclasses) as $state) {
						SearchVariant::activate_state($state);

						$filter = $includeSubclasses ? "" : '"ClassName" = \''.$class."'";
						$singleton = singleton($class);
						$query = $singleton->get($class,$filter,null);
						$dtaQuery = $query->dataQuery();
						$sqlQuery = $dtaQuery->getFinalisedQuery();
						$singleton->extend('augmentSQL',$sqlQuery,$dtaQuery);
						$total = $query->count();

						$statevar = json_encode($state);
						echo "Class: $class, total: $total in state $statevar\n";

						if (strpos(PHP_OS, "WIN") !== false) $statevar = '"'.str_replace('"', '\\"', $statevar).'"';
						else $statevar = "'".$statevar."'";

						for ($offset = 0; $offset < $total; $offset += $this->stat('recordsPerRequest')) {
							echo "$offset..";

							$res = `php $script dev/tasks/$self index=$index class=$class start=$offset variantstate=$statevar`;
							if (isset($_GET['verbose'])) echo "\n  ".preg_replace('/\r\n|\n/', '$0  ', $res)."\n";

							// If we're in dev mode, commit more often for fun and profit
							if (Director::isDev()) Solr::service($index)->commit();
						}
					}
				}

				Solr::service($index)->commit();
			}
		}

		$originalState = SearchVariant::current_state();
	}

	protected function runFrom($index, $class, $start, $variantstate) {
		$classes = $index->getClasses();
		$options = $classes[$class];

		echo "Variant: "; print_r($variantstate);
		SearchVariant::activate_state($variantstate);

		$includeSubclasses = $options['include_children'];
		$filter = $includeSubclasses ? "" : '"ClassName" = \''.$class."'";

		$items = DataList::create($class)->where($filter)->limit($this->stat('recordsPerRequest'), $start);
		foreach ($items as $item) { $index->add($item); $item->destroy(); }
	}

}