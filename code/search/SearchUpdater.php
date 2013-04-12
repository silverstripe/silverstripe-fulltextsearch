<?php

/**
 * This class is responsible for capturing changes to DataObjects and triggering index updates of the resulting dirty index
 * items.
 *
 * Attached automatically by _config calling SearchUpdater#bind_manipulation_capture. Overloads the current database connector's
 * manipulate method - basically we need to capture a manipulation _after_ all the augmentManipulation code (for instance Version's)
 * is run
 *
 * Pretty closely tied to the field structure of SearchIndex.
 *
 * TODO: The way we bind in is awful hacky. The config stuff in 3 will hopefully allow us to force ourselves as the very last
 * augmentManipulation.
 */
class SearchUpdater extends Object {

	const AUTO = 0;
	const DEFERRED = 1;
	const IMMEDIATE = 2;
	const DISABLED = 3;

	/**
	 * How to schedule index updates at the end of the request.
	 *
	 * AUTO = IMMEDIATE if not _many_ dirty records, DEFERRED if _many_ where many is self::$auto_threshold
	 * DEFERRED = Use messagequeue to trigger updating indexes sometime soonish
	 * IMMEDIATE = Update indexes at end of request
	 * DISABLE = Dont update indexes
	 *
	 * If messagequeue module not installed, AUTO => IMMEDIATE and DEFERRED => DISABLED
	 */
	static $update_method = SearchUpdater::AUTO;

	// How many items can be dirty before we defer updates
	static $auto_threshold = 6;

	// The indexing message queue
	static $reindex_queue = "search_indexing";

	static function set_reindexing_queue($queue) { self::$reindex_queue = $queue; }

	/**
	 * Replace the database object with a subclass that captures all manipulations and passes them to us
	 */
	static function bind_manipulation_capture() {
		global $databaseConfig;

		$current = DB::getConn();
		if (!$current || @$current->isManipulationCapture) return; // If not yet set, or its already captured, just return

		$type = get_class($current);
		$file = TEMP_FOLDER."/.cache.SMC.$type";

		if (!is_file($file)) {
			file_put_contents($file, "<?php
				class SearchManipulateCapture_$type extends $type {
					public \$isManipulationCapture = true;

					function manipulate(\$manipulation) {
						\$res = parent::manipulate(\$manipulation);
						SearchUpdater::handle_manipulation(\$manipulation);
						return \$res;
					}
				}
			");
		}

		require_once($file);
		$dbClass = 'SearchManipulateCapture_'.$type;

		$captured = new $dbClass($databaseConfig);
		// The connection might have had it's name changed (like if we're currently in a test)
		$captured->selectDatabase($current->currentDatabase());
		DB::setConn($captured);
	}

	static $dirty = array(); static $dirtycount = 0;

	static function add_dirty_ids($class, $statefulids, $index) {
		$base = ClassInfo::baseDataClass($class);
		$forclass = isset(self::$dirty[$base]) ? self::$dirty[$base] : array();

		foreach ($statefulids as $statefulid) {
			$id = $statefulid['id'];
			$state = $statefulid['state']; $statekey = serialize($state);

			if (!isset($forclass[$statekey])) {
				$forclass[$statekey] = array('state' => $state, 'ids' => array($id => array($index)));
				self::$dirtycount += 1;
			}
			else if (!isset($forclass[$statekey]['ids'][$id])) {
				$forclass[$statekey]['ids'][$id] = array($index);
				self::$dirtycount += 1;
			}
			else if (array_search($index, $forclass[$statekey]['ids'][$id]) === false) {
				$forclass[$statekey]['ids'][$id][] = $index;
				// dirty count stays the same
			}
		}

		self::$dirty[$base] = $forclass;
	}

	static $registered = false;
	
	/**
	 * Called by the SearchManiplateCapture database adapter with every manipulation made against the database.
	 *
	 * Check every index to see what objects need re-inserting into what indexes to keep the index fresh,
	 * but doesn't actually do it yet.
	 *
	 * TODO: This is pretty sensitive to the format of manipulation that DataObject::write produces. Specifically,
	 * it expects the actual class of the object to be present as a table, regardless of if any fields changed in that table
	 * (so a class => array( 'fields' => array() ) item), in order to find the actual class for a set of table manipulations
	 */
	static function handle_manipulation($manipulation) {
		// First, extract any state that is in the manipulation itself
		foreach ($manipulation as $table => $details) {
			$manipulation[$table]['class'] = $table;
			$manipulation[$table]['state'] = array();
		}

		SearchVariant::call('extractManipulationState', $manipulation);

		// Then combine the manipulation back into object field sets
		
		$writes = array();

		foreach ($manipulation as $table => $details) {
			if (!isset($details['id']) || !isset($details['fields'])) continue;

			$id = $details['id'];
			$state = $details['state'];
			$class = $details['class'];
			$fields = $details['fields'];

			$base = ClassInfo::baseDataClass($class);
			$key = "$id:$base:".serialize($state);

			$statefulids = array(array('id' => $id, 'state' => $state));
			
			// Is this the first table for this particular object? Then add an item to $writes
			if (!isset($writes[$key])) $writes[$key] = array('base' => $base, 'class' => $class, 'id' => $id, 'statefulids' => $statefulids, 'fields' => array());
			// Otherwise update the class label if it's more specific than the currently recorded one
			else if (is_subclass_of($class, $writes[$key]['class'])) $writes[$key]['class'] = $class;

			// Update the fields
			foreach ($fields as $field => $value) {
				$writes[$key]['fields']["$class:$field"] = $value;
			}
		}

		// Then extract any state that is needed for the writes

		SearchVariant::call('extractManipulationWriteState', $writes);

		// Then for each write, figure out what objects need updating

		foreach ($writes as $write) {
			// For every index
			foreach (FullTextSearch::get_indexes() as $index => $instance) {
				// If that index as a field from this class
				if (SearchIntrospection::is_subclass_of($write['class'], $instance->dependancyList)) {
					// Get the dirty IDs
					$dirtyids = $instance->getDirtyIDs($write['class'], $write['id'], $write['statefulids'], $write['fields']);

					// Then add then then to the global list to deal with later
					foreach ($dirtyids as $dirtyclass => $ids) {
						if ($ids) self::add_dirty_ids($dirtyclass, $ids, $index);
					}
				}
			}
		}

		// Finally, if we do have some work to do register the shutdown function to actually do the work

		// Don't do it if we're testing - there's no database connection outside the test methods, so we'd
		// just get errors

		if (self::$dirty && !self::$registered && !(class_exists('SapphireTest',false) && SapphireTest::is_running_test())) {
			register_shutdown_function(array("SearchUpdater", "flush_dirty_indexes"));
			self::$registered = true;
		}
	}

	/**
	 * Throw away the recorded dirty IDs without doing anything with them.
	 */
	static function clear_dirty_indexes() {
		self::$dirty = array(); self::$dirtycount = 0;
	}

	/**
	 * Do something with the recorded dirty IDs, where that "something" depends on the value of self::$update_method,
	 * either immediately update the indexes, queue a messsage to update the indexes at some point in the future, or
	 * just throw the dirty IDs away.
	 */
	static function flush_dirty_indexes() {
		if (!self::$dirty) return;

		$method = self::$update_method;

		if (class_exists("MessageQueue")) {
			if ($method == self::AUTO) $method = self::$dirtycount < self::$auto_threshold ? self::IMMEDIATE : self::DEFERRED;
		}
		else {
			if ($method == self::AUTO) $method = self::IMMEDIATE;
			elseif ($method == self::DEFERRED) $method = self::DISABLED;
		}

		switch ($method) {
			case self::IMMEDIATE:
				self::process_dirty_indexes(self::$dirty);
				break;
			case self::DEFERRED:
				MessageQueue::send(
					self::$reindex_queue,
					new MethodInvocationMessage("SearchUpdater", "process_dirty_indexes", self::$dirty)
				);
				break;
			case self::DISABLED:
				// NOP
				break;
		}

		self::clear_dirty_indexes();
	}

	/**
	 * Internal function. Process the passed list of dirty ids. Split from flush_dirty_indexes so it can be called both
	 * directly and via messagequeue message.
	 */
	static function process_dirty_indexes($dirty) {
		$indexes = FullTextSearch::get_indexes();
		$dirtyindexes = array();

		$originalState = SearchVariant::current_state();

		foreach ($dirty as $base => $statefulids) {
			if (!$statefulids) continue;

			foreach ($statefulids as $statefulid) {
				$state = $statefulid['state'];
				$ids = $statefulid['ids'];

				SearchVariant::activate_state($state);

				$objs = DataObject::get($base, '"'.$base.'"."ID" IN ('.implode(',', array_keys($ids)).')');
				if ($objs) foreach ($objs as $obj) {
					foreach ($ids[$obj->ID] as $index) { 
						if (!$indexes[$index]->variantStateExcluded($state)) { 
							$indexes[$index]->add($obj); 
							$dirtyindexes[$index] = $index; 
						}
					}
					unset($ids[$obj->ID]);
				}

				foreach ($ids as $id => $fromindexes) {
					foreach ($fromindexes as $index) { 
						if (!$indexes[$index]->variantStateExcluded($state)) {
							$indexes[$index]->delete($base, $id, $state); 
							$dirtyindexes[$index] = $index; 	
						}
					}
				}
			}
		}

		foreach ($dirtyindexes as $index) {
			$indexes[$index]->commit();
		}

		SearchVariant::activate_state($originalState);
	}
}

class SearchUpdater_BindManipulationCaptureFilter implements RequestFilter {
	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {
		SearchUpdater::bind_manipulation_capture();
	}

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		/* NOP */
	}
}
