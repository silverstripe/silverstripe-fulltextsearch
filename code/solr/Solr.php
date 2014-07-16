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
	 *      remotepath - The path that the Solr server will read the index configurations from
	 */
	protected static $solr_options = array();

	/** A cache of solr_options with the defaults all merged in */
	protected static $merged_solr_options = null;

	/**
	 * Update the configuration for Solr. See $solr_options for a discussion of the accepted array keys
	 * @param array $options - The options to update
	 */
	static function configure_server($options = array()) {
		self::$solr_options = array_merge(self::$solr_options, $options);
		self::$merged_solr_options = null;

		self::$service_singleton = null;
		self::$service_core_singletons = array();
	}

	/**
	 * Get the configured Solr options with the defaults all merged in
	 * @return array - The merged options
	 */
	static function solr_options() {
		if (self::$merged_solr_options) return self::$merged_solr_options;

		$defaults = array(
			'host' => 'localhost',
			'port' => 8983,
			'path' => '/solr',
			'version' => '4'
		);

		// Build some by-version defaults
		$version = isset(self::$solr_options['version']) ? self::$solr_options['version'] : $defaults['version'];

		if (version_compare($version, '4', '>=')){
			$versionDefaults = array(
				'service' => 'Solr4Service',
				'extraspath' => Director::baseFolder().'/fulltextsearch/conf/solr/4/extras/',
				'templatespath' => Director::baseFolder().'/fulltextsearch/conf/solr/4/templates/',
			);
		}
		else {
			$versionDefaults = array(
				'service' => 'Solr3Service',
				'extraspath' => Director::baseFolder().'/fulltextsearch/conf/solr/3/extras/',
				'templatespath' => Director::baseFolder().'/fulltextsearch/conf/solr/3/templates/',
			);
		}

		return (self::$merged_solr_options = array_merge($defaults, $versionDefaults, self::$solr_options));
	}


	static function set_service_class($class) {
		user_error('set_service_class is deprecated - pass as part of $options to configure_server', E_USER_WARNING);
		self::configure_server(array('service' => $class));
	}

	/** @var SolrService | null - The instance of SolrService for core management */
	static protected $service_singleton = null;
	/** @var [SolrService_Core] - The instances of SolrService_Core for each core */
	static protected $service_core_singletons = array();

	static function service($core = null) {
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

	static function get_indexes() {
		return FullTextSearch::get_indexes('SolrIndex');
	}

	/**
	 * Include the thirdparty Solr client api library. Done this way to avoid issues where code is called in
	 * mysite/_config before fulltextsearch/_config has a change to update the include path.
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
		$options = Solr::solr_options();

		if (!isset($options['indexstore']) || !($indexstore = $options['indexstore'])) {
			user_error('No index configuration for Solr provided', E_USER_ERROR);
		}

		// Find the IndexStore handler, which will handle uploading config files to Solr
		$mode = $indexstore['mode'];

		if ($mode == 'file') {
			$store = new SolrConfigStore_File($indexstore);
		} elseif ($mode == 'webdav') {
			$store = new SolrConfigStore_WebDAV($indexstore);
		} elseif (ClassInfo::exists($mode) && ClassInfo::classImplements($mode, 'SolrConfigStore')) {
			$store = new $mode($indexstore);
		} else {
			user_error('Unknown Solr index mode '.$indexstore['mode'], E_USER_ERROR);
		}
		
		foreach ($indexes as $instance) {
			$index = $instance->getIndexName();
			echo "Configuring $index. \n"; flush();

			try {
				// Upload the config files for this index
				echo "Uploading configuration ... \n"; flush();

				$store->uploadString($index, 'schema.xml', (string)$instance->generateSchema());

				foreach (glob($instance->getExtrasPath().'/*') as $file) {
					if (is_file($file)) $store->uploadFile($index, $file);
				}

				// Then tell Solr to use those config files
				if ($service->coreIsActive($index)) {
					echo "Reloading core ... \n";
					$service->coreReload($index);
				} else {
					echo "Creating core ... \n";
					$service->coreCreate($index, $store->instanceDir($index));
				}

				// And done
				echo "Done\n";

			} catch(Exception $e) {
				// We got an exception. Warn, but continue to next index.
				echo "Failure: " . $e->getMessage() . "\n"; flush();
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
		$hasTranslatable = false;


		// if Translatable installed
		if(class_exists('Translatable') && singleton('SiteTree')->hasExtension('Translatable')) {
			$hasTranslatable = true;
		}

		$originalState = SearchVariant::current_state();

		if (isset($_GET['start'])) {
			$this->runFrom(singleton($_GET['index']), $_GET['class'], $_GET['start'], json_decode($_GET['variantstate'], true));
		
		} else {
			foreach(array('framework','sapphire') as $dirname) {
				$script = sprintf("%s%s$dirname%scli-script.php", BASE_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
				if(file_exists($script)) {
					break;
				}
			}
			$class = get_class($this);

			foreach (Solr::get_indexes() as $index => $instance) {
				$locale = false;

				// Limit to a specific index
				// eg: sake dev/tasks/Solr_Reindex index=ChineseSiteSearchIndex
				if($request->getVar('index') && $request->getVar('index') != $instance->getIndexName()) {
					continue;
				}

				// set the locale if the index requires it
				if($hasTranslatable) {
					$limitToLocale = Config::inst()->get($index, 'limitToLocale');
					if($limitToLocale && i18n::validate_locale($limitToLocale)) {
						$locale = $limitToLocale;
					}
				}

				echo "\r\n\r\nRebuilding {$instance->getIndexName()}\r\n";

				$classes = $instance->getClasses();

				if($request->getVar('class')) {
					$limitClasses = explode(',', $request->getVar('class'));
					$classes = array_intersect_key($classes, array_combine($limitClasses, $limitClasses));
				}

				Solr::service($index)->deleteByQuery('ClassHierarchy:(' . implode(' OR ', array_keys($classes)) . ')');

				foreach ($classes as $class => $options) {
					$includeSubclasses = $options['include_children'];
					
					foreach (SearchVariant::reindex_states($class, $includeSubclasses) as $state) {
						if ($instance->variantStateExcluded($state)) continue;
						
						SearchVariant::activate_state($state);

						$filter = $includeSubclasses ? "" : '"ClassName" = \''.$class."'";
						$singleton = singleton($class);
						$query = $singleton->get($class,$filter,null);
						$dtaQuery = $query->dataQuery();
						$sqlQuery = $dtaQuery->getFinalisedQuery();
						$singleton->extend('augmentSQL',$sqlQuery,$dtaQuery);
						$total = $query->count();

						$statevar = json_encode($state);
						echo "Class: $class, total: $total";
						echo ($statevar) ? " in state $statevar\n" : "\n";

						if (strpos(PHP_OS, "WIN") !== false) $statevar = '"'.str_replace('"', '\\"', $statevar).'"';
						else $statevar = "'".$statevar."'";

						for ($offset = 0; $offset < $total; $offset += $this->stat('recordsPerRequest')) {
							echo "$offset..";

							$cmd = "php $script dev/tasks/$self index=$index class=$class start=$offset variantstate=$statevar";
							
							if($verbose) {
								echo "\n  Running '$cmd'\n";
								$cmd .= " verbose=1";
							}

							if($locale) {
								echo "Locale: $locale \n";
								$cmd .= " locale=$locale";
							}
							
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
		$verbose = isset($_GET['verbose']);

		SearchVariant::activate_state($variantstate);

		$includeSubclasses = $options['include_children'];
		$filter = $includeSubclasses ? "" : '"ClassName" = \''.$class."'";

		$items = DataList::create($class)
			->where($filter)
			->limit($this->stat('recordsPerRequest'), $start);

		if($verbose) echo "Adding $class";
		foreach ($items as $item) {
			if($verbose) echo $item->ID . ' ';

			$index->add($item);

			$item->destroy(); 
		}

		if($verbose) echo "Done ";
	}

}
