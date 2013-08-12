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
	 * Optional fields:
	 * extraspath (default: <basefolder>/fulltextsearch/conf/extras/) - Absolute path to 
	 *   the folder containing templates which are used for generating the schema and field definitions.
	 * templates (default: <basefolder>/fulltextsearch/conf/templates/) - Absolute path to 
	 *   the configuration default files, e.g. solrconfig.xml.
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
			'path' => '/solr',
			'extraspath' => Director::baseFolder().'/fulltextsearch/conf/extras/',
			'templatespath' => Director::baseFolder().'/fulltextsearch/conf/templates/',
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
	 * before fulltextsearch/_config has a change to update the include path.
	 */
	static function include_client_api() {
		static $included = false;

		if (!$included) {
			set_include_path(get_include_path() . PATH_SEPARATOR . Director::baseFolder() . '/fulltextsearch/thirdparty/solr-php-client');
			require_once('Apache/Solr/Service.php');
			require_once('Apache/Solr/Document.php');

			$included = true;
		}
	}

}

class Solr_Configure extends BuildTask {

	public function run($request) {
		$service = Solr::service();
		$indexes = Solr::get_indexes();

		if (!isset(Solr::$solr_options['indexstore']) || !($index = Solr::$solr_options['indexstore'])) {
			user_error('No index configuration for Solr provided', E_USER_ERROR);
		}

		$remote = null;

		switch ($index['mode']) {
			case 'file':
				$local = $index['path'];
				$remote = isset($index['remotepath']) ? $index['remotepath'] : $local;
				
				foreach ($indexes as $index => $instance) {
					$sourceDir = $instance->getExtrasPath();
					$targetDir = "$local/$index/conf";
					if (!is_dir($targetDir)) {
						$worked = @mkdir($targetDir, 0770, true);
						if(!$worked) {
							echo sprintf('Failed creating target directory %s, please check permissions', $targetDir);
							return;
						}
					}

					file_put_contents("$targetDir/schema.xml", $instance->generateSchema());

					echo sprintf("Copying %s to %s...", $sourceDir, $targetDir);
					foreach (glob($sourceDir . '/*') as $file) {
						if (is_file($file)) copy($file, $targetDir.'/'.basename($file));
					}
					echo "done\n";
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

				foreach ($indexes as $index => $instance) {
					$indexdir = "$url/$index";
					if (!WebDAV::exists($indexdir)) WebDAV::mkdir($indexdir);

					$sourceDir = $instance->getExtrasPath();
					$targetDir = "$url/$index/conf";
					if (!WebDAV::exists($targetDir)) WebDAV::mkdir($targetDir);

					WebDAV::upload_from_string($instance->generateSchema(), "$targetDir/schema.xml");

					echo sprintf("Copying %s to %s (via WebDAV)...", $sourceDir, $targetDir);
					foreach (glob($sourceDir . '/*') as $file) {
						if (is_file($file)) WebDAV::upload_from_file($file, $targetDir.'/'.basename($file));
					}
					echo "done\n";
				}
					
				break;

			default:
				user_error('Unknown Solr index mode '.$index['mode'], E_USER_ERROR);
		}

		foreach ($indexes as $index => $instance) {
			$indexName = $instance->getIndexName();

			if ($service->coreIsActive($index)) {
				echo "Reloading configuration...";
				$service->coreReload($index);
				echo "done\n";
			} else {
				echo "Creating configuration...";
				$instanceDir = $indexName;
				if ($remote) {
					$instanceDir = "$remote/$instanceDir";
				}
				$service->coreCreate($indexName, $instanceDir);
				echo "done\n";
			}
		}
	}
}

class Solr_Reindex extends BuildTask {
	static $recordsPerRequest = 200;

	public function run($request) {
		increase_time_limit_to();
		$self = get_class($this);
		$verbose = isset($_GET['verbose']);

		$originalState = SearchVariant::current_state();

		if (isset($_GET['start'])) {
			$this->runFrom(singleton($_GET['index']), $_GET['class'], $_GET['start'], json_decode($_GET['variantstate'], true));
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

				$classes = $instance->getClasses();
				if($request->getVar('class')) {
					$limitClasses = explode(',', $request->getVar('class'));
					$classes = array_intersect_key($classes, array_combine($limitClasses, $limitClasses));
				}

				Solr::service($index)->deleteByQuery('ClassHierarchy:(' . implode(' OR ', array_keys($classes)) . ')');

				foreach ($classes as $class => $options) {
					
					foreach (SearchVariant::reindex_states($class, $options['include_children']) as $state) {
						if ($instance->variantStateExcluded($state)) continue;
						
						SearchVariant::activate_state($state);

						$list = ($options['list']) ? $options['list'] : DataList::create($class);
						if (!$options['include_children']) $list = $list->filter('ClassName', $class);
						$dtaQuery = $list->dataQuery();						
						$sqlQuery = $dtaQuery->getFinalisedQuery();
						singleton($class)->extend('augmentSQL',$sqlQuery,$dtaQuery);
						$total = $list->count();

						$statevar = json_encode($state);
						echo "Class: $class, total: $total";
						echo ($statevar) ? " in state $statevar\n" : "\n";

						if (strpos(PHP_OS, "WIN") !== false) $statevar = '"'.str_replace('"', '\\"', $statevar).'"';
						else $statevar = "'".$statevar."'";

						for ($offset = 0; $offset < $total; $offset += $this->stat('recordsPerRequest')) {
							echo "$offset..";
							
							$cmd = "php $script dev/tasks/$self index=$index class=$class start=$offset variantstate=$statevar";
							if($verbose) echo "\n  Running '$cmd'\n";
							$res = $verbose ? passthru($cmd) : `$cmd`;
							if($verbose) echo "  ".preg_replace('/\r\n|\n/', '$0  ', $res)."\n";

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

		SearchVariant::activate_state($variantstate);

		$list = ($options['list']) ? $options['list'] : DataList::create($class);
		if($options['include_children']) $list = $list->filter('ClassName', $class);
		$list = $list->limit($this->stat('recordsPerRequest'), $start);
		foreach ($list as $item) { $index->add($item); $item->destroy(); }
	}

}
