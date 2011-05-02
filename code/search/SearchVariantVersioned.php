<?php

class SearchVariantVersioned extends SearchVariant {

	function appliesTo($class, $includeSubclasses) {
		return SearchIntrospection::has_extension($class, 'Versioned', $includeSubclasses);
	}

	function currentState() { return Versioned::current_stage(); }
	function reindexStates() { return array('Stage', 'Live'); }
	function activateState($state) { Versioned::reading_stage($state); }

	function alterDefinition($base, $index) {
		$self = get_class($this);

		$index->filterFields['_versionedstage'] = array(
			'name' => '_versionedstage',
			'field' => '_versionedstage',
			'fullfield' => '_versionedstage',
			'base' => $base,
			'origin' => $base,
			'type' => 'String',
			'lookup_chain' => array(array('call' => 'variant', 'variant' => $self, 'method' => 'currentState'))
		);
	}

	function alterQuery($query) {
		$stage = Versioned::current_stage();
		$query->filter('_versionedstage', array($stage, SearchQuery::$missing));
	}
	
	function extractManipulationState(&$manipulation) {
		$self = get_class($this);
		
		foreach ($manipulation as $table => $details) {
			$class = $details['class'];
			$stage = 'Stage';

			if (preg_match('/^(.*)_Live$/', $table, $matches)) {
				$class = $matches[1];
				$stage = 'Live';
			}

			if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
				$manipulation[$table]['class'] = $class;
				$manipulation[$table]['state'][$self] = $stage;
			}
		}
	}

	function extractStates(&$table, &$ids, &$fields) {
		$class = $table;
		$suffix = null;


		if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
			$table = $class;
			$self = get_class($this);

			foreach ($ids as $i => $statefulid) {
				$ids[$i]['state'][$self] = $suffix ? $suffix : 'Stage';
			}
		}
	}

}
