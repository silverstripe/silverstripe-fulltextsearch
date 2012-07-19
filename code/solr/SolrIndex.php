<?php

Solr::include_client_api();

abstract class SolrIndex extends SearchIndex {

	static $fulltextTypeMap = array(
		'*' => 'text',
		'HTMLVarchar' => 'htmltext',
		'HTMLText' => 'htmltext'
	);

	static $filterTypeMap = array(
		'*' => 'string',
		'Boolean' => 'boolean',
		'Date' => 'tdate',
		'SSDatetime' => 'tdate',
		'SS_Datetime' => 'tdate',
		'ForeignKey' => 'tint',
		'Int' => 'tint',
		'Float' => 'tfloat',
		'Double' => 'tdouble'
	);

	function generateSchema() {
		return $this->renderWith(Director::baseFolder() . '/solr/conf/templates/schema.ss');
	}

	function getIndexName() {
		return get_class($this);
	}

	function getTypes() {
		return $this->renderWith(Director::baseFolder() . '/solr/conf/templates/types.ss');
	}

	function getFieldDefinitions() {
		$xml = array();
		$stored = Director::isDev() ? "stored='true'" : "stored='false'";

		$xml[] = "";

		// Add the hardcoded field definitions

		$xml[] = "<field name='_documentid' type='string' indexed='true' stored='true' required='true' />";

		$xml[] = "<field name='ID' type='tint' indexed='true' stored='true' required='true' />";
		$xml[] = "<field name='ClassName' type='string' indexed='true' stored='true' required='true' />";
		$xml[] = "<field name='ClassHierarchy' type='string' indexed='true' stored='true' required='true' multiValued='true' />";

		// Add the fulltext collation field

		$xml[] = "<field name='_text' type='htmltext' indexed='true' $stored multiValued='true' />" ;

		// Add the user-specified fields

		foreach ($this->fulltextFields as $name => $field) {
			$type = isset(self::$fulltextTypeMap[$field['type']]) ? self::$fulltextTypeMap[$field['type']] : self::$fulltextTypeMap['*'];
			$xml[] = "<field name='{$name}' type='$type' indexed='true' $stored />";
		}

		foreach ($this->filterFields as $name => $field) {
			if ($field['fullfield'] == 'ID' || $field['fullfield'] == 'ClassName') continue;

			$multiValued = (isset($field['multi_valued']) && $field['multi_valued']) ? "multiValued='true'" : '';

			$type = isset(self::$filterTypeMap[$field['type']]) ? self::$filterTypeMap[$field['type']] : self::$filterTypeMap['*'];
			$xml[] = "<field name='{$name}' type='{$type}' indexed='true' $stored $multiValued />";
		}

		foreach ($this->sortFields as $name => $field) {
			if ($field['fullfield'] == 'ID' || $field['fullfield'] == 'ClassName') continue;
			
			$multiValued = (isset($field['multi_valued']) && $field['multi_valued']) ? "multiValued='true'" : '';

			$type = self::$sortTypeMap[$field['type']];
			$xml[] = "<field name='{$name}' type='{$type}' indexed='true' $stored $multiValued />";
		}
		
		return implode("\n\t\t", $xml);
	}

	function getCopyFieldDefinitions() {
		$xml = array();

		foreach ($this->fulltextFields as $name => $field) {
			$xml[] = "<copyField source='{$name}' dest='_text' />";
		}

		return implode("\n\t", $xml);
	}

	protected function _addField($doc, $object, $field) {
		$class = get_class($object);
		if ($class != $field['origin'] && !is_subclass_of($class, $field['origin'])) return;

		$value = $this->_getFieldValue($object, $field);
		$type = isset(self::$filterTypeMap[$field['type']]) ? self::$filterTypeMap[$field['type']] : self::$filterTypeMap['*'];

		if (is_array($value)) foreach($value as $sub) {
			/* Solr requires dates in the form 1995-12-31T23:59:59Z */
			if ($type == 'tdate') $sub = gmdate('Y-m-d\TH:i:s\Z', strtotime($sub));
			/* Solr requires numbers to be valid if presented, not just empty */
			if (($type == 'tint' || $type == 'tfloat' || $type == 'tdouble') && !is_numeric($sub)) continue;
			
			$doc->addField($field['name'], $sub);
		}

		else {
			/* Solr requires dates in the form 1995-12-31T23:59:59Z */
			if ($type == 'tdate') $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
			/* Solr requires numbers to be valid if presented, not just empty */
			if (($type == 'tint' || $type == 'tfloat' || $type == 'tdouble') && !is_numeric($value)) return;

			$doc->setField($field['name'], $value);
		}
	}

	protected function _addAs($object, $base, $options) {
		$includeSubs = $options['include_children'];

		$doc = new Apache_Solr_Document();

		// Always present fields

		$doc->setField('_documentid', $this->getDocumentID($object, $base, $includeSubs));
		$doc->setField('ID', $object->ID);
		$doc->setField('ClassName', $object->ClassName);

		foreach (SearchIntrospection::hierarchy(get_class($object), false) as $class) $doc->addField('ClassHierarchy', $class);

		// Add the user-specified fields

		foreach ($this->getFieldsIterator() as $name => $field) {
			if ($field['base'] == $base) $this->_addField($doc, $object, $field);
		}

		Solr::service(get_class($this))->addDocument($doc);
	}

	function add($object) {
		$class = get_class($object);

		foreach ($this->getClasses() as $searchclass => $options) {
			if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) {
				$this->_addAs($object, $searchclass, $options);
			}
		}
	}

	function canAdd($class) {
		foreach ($this->classes as $searchclass => $options) {
			if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) return true;
		}

		return false;
	}

	function delete($base, $id, $state) {
		$documentID = $this->getDocumentIDForState($base, $id, $state);
		Solr::service(get_class($this))->deleteById($documentID);
	}

	function commit() {
		Solr::service(get_class($this))->commit(false, false, false);
	}

	public function search($query, $offset = -1, $limit = -1) {
		$service = Solr::service(get_class($this));

		SearchVariant::with(count($query->classes) == 1 ? $query->classes[0]['class'] : null)->call('alterQuery', $query, $this);

		$q = array();
		$fq = array();

		// Build the search itself

		foreach ($query->search as $search) {
			$text = $search['text'];
			preg_match_all('/"[^"]*"|\S+/', $text, $parts);

			$fuzzy = $search['fuzzy'] ? '~' : '';

			foreach ($parts[0] as $part) {
				if ($search['fields']) {
					$searchq = array();
					foreach ($search['fields'] as $field) {
						$searchq[] = "{$field}:".$part.$fuzzy;
					}
					$q[] = '+('.implode(' ', $searchq).')';
				}
				else {
					$q[] = '+'.$part;
				}
			}
		}

		// Filter by class if requested

		$classq = array();

		foreach ($query->classes as $class) {
			if ($class['includeSubclasses']) $classq[] = 'ClassHierarchy:'.$class['class'];
			else $classq[] = 'ClassName:'.$class['class'];
		}

		if ($classq) $fq[] = '+('.implode(' ', $classq).')';

		// Filter by filters

		foreach ($query->require as $field => $values) {
			$requireq = array();

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$requireq[] = "(*:* -{$field}:[* TO *])";
				}
				else if ($value === SearchQuery::$present) {
					$requireq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$requireq[] = "$field:[$start TO $end]";
				}
				else {
					$requireq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = '+('.implode(' ', $requireq).')';
		}

		foreach ($query->exclude as $field => $values) {
			$excludeq = array();
			$missing = false;

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$missing = true;
				}
				else if ($value === SearchQuery::$present) {
					$excludeq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$excludeq[] = "$field:[$start TO $end]";
				}
				else {
					$excludeq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = ($missing ? "+{$field}:[* TO *] " : '') . '-('.implode(' ', $excludeq).')';
		}

		if ($q) header('X-Query: '.implode(' ', $q));
		if ($fq) header('X-Filters: "'.implode('", "', $fq).'"');

		if ($offset == -1) $offset = $query->start;
		if ($limit == -1) $limit = $query->limit;
		if ($limit == -1) $limit = SearchQuery::$default_page_size;

		$res = $service->search($q ? implode(' ', $q) : '*:*', $offset, $limit, array('fq' => implode(' ', $fq)), Apache_Solr_Service::METHOD_POST);

		$results = array();

		foreach ($res->response->docs as $doc) {
			$result = DataObject::get_by_id($doc->ClassName, $doc->ID);
			if ($result) $results[] = $result;
		}

		$ret = array();
		$ret['Matches'] = new DataObjectSet($results);
		$ret['Matches']->setPageLimits($offset, $limit, $res->numFound);

		return new ArrayData($ret);
	}
}
