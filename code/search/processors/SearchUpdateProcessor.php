<?php

abstract class SearchUpdateProcessor {
	function __construct() {
		$this->dirty = array();
		$this->dirtyindexes = array();
	}

	public function addDirtyIDs($class, $statefulids, $index) {
		$base = ClassInfo::baseDataClass($class);
		$forclass = isset($this->dirty[$base]) ? $this->dirty[$base] : array();

		foreach ($statefulids as $statefulid) {
			$id = $statefulid['id'];
			$state = $statefulid['state']; $statekey = serialize($state);

			if (!isset($forclass[$statekey])) {
				$forclass[$statekey] = array('state' => $state, 'ids' => array($id => array($index)));
			}
			else if (!isset($forclass[$statekey]['ids'][$id])) {
				$forclass[$statekey]['ids'][$id] = array($index);
			}
			else if (array_search($index, $forclass[$statekey]['ids'][$id]) === false) {
				$forclass[$statekey]['ids'][$id][] = $index;
				// dirty count stays the same
			}
		}

		$this->dirty[$base] = $forclass;
	}

	public function process() {
		$indexes = FullTextSearch::get_indexes();
		$originalState = SearchVariant::current_state();

		foreach ($this->dirty as $base => $statefulids) {
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
							$this->dirtyindexes[$index] = $index;
						}
					}
					unset($ids[$obj->ID]);
				}

				foreach ($ids as $id => $fromindexes) {
					foreach ($fromindexes as $index) {
						if (!$indexes[$index]->variantStateExcluded($state)) {
							$indexes[$index]->delete($base, $id, $state);
							$this->dirtyindexes[$index] = $index;
						}
					}
				}
			}
		}

		SearchVariant::activate_state($originalState);

		// Then commit all indexes
		foreach ($this->dirtyindexes as $index) {
			if ($indexes[$index]->commit() === false) return false;
		}
	}

	abstract public function triggerProcessing();
}



