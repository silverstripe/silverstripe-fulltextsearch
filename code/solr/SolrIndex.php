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

	static $sortTypeMap = array();

	protected $extrasPath = null;

	protected $templatesPath = null;
	/**
	 * @return String Absolute path to the folder containing
	 * templates which are used for generating the schema and field definitions.
	 */
	function getTemplatesPath() {
		return $this->templatesPath ? $this->templatesPath : Solr::$solr_options['templatespath'];
	}

	/**
	 * @return String Absolute path to the configuration default files,
	 * e.g. solrconfig.xml.
	 */
	function getExtrasPath() {
		return $this->extrasPath ? $this->extrasPath : Solr::$solr_options['extraspath'];
	}

	function generateSchema() {
		return $this->renderWith($this->getTemplatesPath() . '/schema.ss');
	}

	function getIndexName() {
		return get_class($this);
	}

	function getTypes() {
		return $this->renderWith($this->getTemplatesPath() . '/types.ss');
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

			$typeMap = array_merge(self::$filterTypeMap, self::$sortTypeMap);
			$type = isset($typeMap[$field['type']]) ? $typeMap[$field['type']] : $typeMap['*'];
			
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
			if ($type == 'tdate') {
				if(!$sub) continue;
				$sub = gmdate('Y-m-d\TH:i:s\Z', strtotime($sub));
			}

			/* Solr requires numbers to be valid if presented, not just empty */
			if (($type == 'tint' || $type == 'tfloat' || $type == 'tdouble') && !is_numeric($sub)) continue;
			
			$doc->addField($field['name'], $sub);
		}

		else {
			/* Solr requires dates in the form 1995-12-31T23:59:59Z */
			if ($type == 'tdate') {
				if(!$value) return;
				$value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
			}

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

		try {
			$this->getService()->addDocument($doc);
		} catch (Exception $e) {
			SS_Log::log($e, SS_Log::WARN);
			return false;
		}

		return $doc;
	}

	function add($object) {
		$class = get_class($object);
		$docs = array();

		foreach ($this->getClasses() as $searchclass => $options) {
			if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) {
				$docs[] = $this->_addAs($object, $searchclass, $options);
			}
		}

		return $docs;
	}

	function canAdd($class) {
		foreach ($this->classes as $searchclass => $options) {
			if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) return true;
		}

		return false;
	}

	function delete($base, $id, $state) {
		$documentID = $this->getDocumentIDForState($base, $id, $state);

		try {
			$this->getService()->deleteById($documentID);
		} catch (Exception $e) {
			SS_Log::log($e, SS_Log::WARN);
			return false;
		}
	}

	function commit() {
		try {
			$this->getService()->commit(false, false, false);
		} catch (Exception $e) {
			SS_Log::log($e, SS_Log::WARN);
			return false;
		}
	}

	/**
	 * @param SearchQuery $query
	 * @param integer $offset
	 * @param integer $limit
	 * @param  Array $params Extra request parameters passed through to Solr
	 * @return ArrayData Map with the following keys: 
	 *  - 'Matches': ArrayList of the matched object instances
	 */
	public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = array()) {
		$service = $this->getService();

		SearchVariant::with(count($query->classes) == 1 ? $query->classes[0]['class'] : null)->call('alterQuery', $query, $this);

		$q = array();
		$fq = array();

		// Build the search itself

		foreach ($query->search as $search) {
			$text = $search['text'];
			preg_match_all('/"[^"]*"|\S+/', $text, $parts);

			$fuzzy = $search['fuzzy'] ? '~' : '';

			foreach ($parts[0] as $part) {
				$fields = (isset($search['fields'])) ? $search['fields'] : array();
				if(isset($search['boost'])) $fields = array_merge($fields, array_keys($search['boost']));
				if ($fields) {
					$searchq = array();
					foreach ($fields as $field) {
						$boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
						$searchq[] = "{$field}:".$part.$fuzzy.$boost;
					}
					$q[] = '+('.implode(' OR ', $searchq).')';
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

		if(!headers_sent()) {
			if ($q) header('X-Query: '.implode(' ', $q));
			if ($fq) header('X-Filters: "'.implode('", "', $fq).'"');
		}

		if ($offset == -1) $offset = $query->start;
		if ($limit == -1) $limit = $query->limit;
		if ($limit == -1) $limit = SearchQuery::$default_page_size;

		$params = array_merge($params, array('fq' => implode(' ', $fq)));

		$res = $service->search(
			$q ? implode(' ', $q) : '*:*', 
			$offset, 
			$limit, 
			$params, 
			Apache_Solr_Service::METHOD_POST
		);

		$results = new ArrayList();
		if($res->getHttpStatus() >= 200 && $res->getHttpStatus() < 300) {
			foreach ($res->response->docs as $doc) {
				$result = DataObject::get_by_id($doc->ClassName, $doc->ID);
				if($result) {
					$results->push($result);

					// Add highlighting (optional)
					$docId = $doc->_documentid;
					if($res->highlighting && $res->highlighting->$docId) {
						// TODO Create decorator class for search results rather than adding arbitrary object properties
						// TODO Allow specifying highlighted field, and lazy loading
						// in case the search API needs another query (similar to SphinxSearchable->buildExcerpt()).
						$combinedHighlights = array();
						foreach($res->highlighting->$docId as $field => $highlights) {
							$combinedHighlights = array_merge($combinedHighlights, $highlights);
						}
						$result->Excerpt = implode(' ... ', $combinedHighlights);
					}
				}
			}
			$numFound = $res->response->numFound;
		} else {
			$numFound = 0;
		}
		
		$ret = array();
		$ret['Matches'] = new PaginatedList($results);
		$ret['Matches']->setLimitItems(false);
		// Tell PaginatedList how many results there are
		$ret['Matches']->setTotalItems($numFound);
		// Results for current page start at $offset
		$ret['Matches']->setPageStart($offset);
		// Results per page
		$ret['Matches']->setPageLength($limit);
		// Suggestions (requires custom setup, assumes spellcheck.collate=true)
		if(isset($res->spellcheck->suggestions->collation)) {
			$ret['Suggestion'] = $res->spellcheck->suggestions->collation;
		}

		return new ArrayData($ret);
	}

	protected $service;

	/**
	 * @return SolrService
	 */
	public function getService() {
		if(!$this->service) $this->service = Solr::service(get_class($this));
		return $this->service;
	}

	public function setService(SolrService $service) {
		$this->service = $service;
		return $this;
	}
}
