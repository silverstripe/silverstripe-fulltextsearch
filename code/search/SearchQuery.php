<?php

/**
 * Represents a search query
 *
 * API very much still in flux.
 */
class SearchQuery extends ViewableData {

	static $missing = null;
	
	static $present = null;

	static $default_page_size = 10;

	/** These are public, but only for index & variant access - API users should not manually access these */

	public $search = array();

	public $classes = array();

	public $require = array();

	public $exclude = array();

	protected $start = 0;

	protected $limit = -1;

	function __construct() {
		if (self::$missing === null) self::$missing = new stdClass();
		if (self::$present === null) self::$present = new stdClass();
	}

	/**
	 * @param  String $text Search terms. Exact format (grouping, boolean expressions, etc.) depends on the search implementation.
	 * @param  array $fields Limits the search to specific fields (using composite field names)
	 * @param  array  $boost  Map of composite field names to float values. The higher the value,
	 * the more important the field gets for relevancy.
	 */
	function addSearchTerm($text, $fields = null, $boost = array()) {
		$this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => false);
		return $this;
	}

	/**
	 * Similar to {@link addSearchTerm()}, but uses stemming and other similarity algorithms
	 * to find the searched terms. For example, a term "fishing" would also likely find results
	 * containing "fish" or "fisher". Depends on search implementation.
	 * 
	 * @param  String $text See {@link addSearchTerm()}
	 * @param  array $fields See {@link addSearchTerm()}
	 * @param  array $boost See {@link addSearchTerm()}
	 */
	function addFuzzySearchTerm($text, $fields = null, $boost = array()) {
		$this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => true);
		return $this;
	}

	function getSearchTerms() {
		return $this->search;
	}

	/**
	 * Limit search to a specific class. Includes subclasses by default.
	 * 
	 * @param String  $class
	 * @param boolean $includeSubclasses
	 */
	function addClassFilter($class, $includeSubclasses = true) {
		$this->classes[] = array('class' => $class, 'includeSubclasses' => $includeSubclasses);
		return $this;
	}

	function getClassFilters() {
		return $this->classes;
	}

	/**
	 * Similar to {@link addSearchTerm()}, but typically used to further narrow down
	 * based on other facets which don't influence the field relevancy.
	 * 
	 * @param  String $field Composite name of the field
	 * @param  Mixed $values Scalar value, array of values, or an instance of SearchQuery_Range
	 */
	function addFilter($field, $values) {
		$requires = isset($this->require[$field]) ? $this->require[$field] : array();
		$values = is_array($values) ? $values : array($values);
		$this->require[$field] = array_merge($requires, $values);
		return $this;
	}

	function getFilters() {
		return $this->require;
	}

	/**
	 * Excludes results which match these criteria, inverse of {@link filter()}.
	 * 
	 * @param  String $field
	 * @param  mixed $values
	 */
	function addExclude($field, $values) {
		$excludes = isset($this->exclude[$field]) ? $this->exclude[$field] : array();
		$values = is_array($values) ? $values : array($values);
		$this->exclude[$field] = array_merge($excludes, $values);
		return $this;
	}

	function getExcludes() {
		return $this->exclude;
	}

	function setStart($start) {
		$this->start = $start;
		return $this;
	}

	function getStart() {
		return $this->start;
	}

	function setLimit($limit) {
		$this->limit = $limit;
		return $this;
	}

	function getLimit() {
		return $this->limit;
	}

	function setPageSize($page) {
		$this->start = $page * self::$default_page_size;
		$this->limit = self::$default_page_size;
		return $this;
	}

	public function getPageSize() {
		return $this->limit;
	}

	function isFiltered() {
		return $this->search || $this->classes || $this->require || $this->exclude;
	}

	function __toString() {
		return "Search Query\n";
	}

	/**
	 * @deprecated
	 */
	function search($text, $fields = null, $boost = array()) {
		Deprecation::notice('2.0', 'Use addSearchTerm()');
		return $this->addSearchTerm($text, $fields, $boost);
	}

	/**
	 * @deprecated
	 */
	function fuzzysearch($text, $fields = null, $boost = array()) {
		Deprecation::notice('2.0', 'Use addFuzzySearchTerm()');
		return $this->addFuzzySearchTerm($text, $fields, $boost);
	}

	/**
	 * @deprecated
	 */
	function filter($field, $values) {
		Deprecation::notice('2.0', 'Use addFilter()');
		return $this->addFilter($field, $values);
	}

	/**
	 * @deprecated
	 */
	function inClass($class, $includeSubclasses = true) {
		Deprecation::notice('2.0', 'Use addClassFilter()');
		return $this->addClassFilter($class, $includeSubclasses);
	}

	/**
	 * @deprecated
	 */
	function exclude($field, $values) {
		Deprecation::notice('2.0', 'Use addExclude()');
		return $this->addExclude($field, $values);
	}

	/**
	 * @deprecated
	 */
	function start($start) {
		Deprecation::notice('2.0', 'Use setStart()');
		return $this->setStart($start);
	}

	/**
	 * @deprecated
	 */
	function limit($limit) {
		Deprecation::notice('2.0', 'Use setLimit()');
		return $this->setLimit($limit);
	}

	/**
	 * @deprecated
	 */
	function page($page) {
		Deprecation::notice('2.0', 'Use setPageSize()');
		return $this->setPageSize($page);
	}
}

/**
 * Create one of these and pass as one of the values in filter or exclude to filter or exclude by a (possibly
 * open ended) range
 */
class SearchQuery_Range {

	public $start = null;
	public $end = null;

	function __construct($start = null, $end = null) {
		$this->start = $start;
		$this->end = $end;
	}

	function setStart($start) {
		$this->start = $start;
		return $this;
	}

	function setEnd($end) {
		$this->end = $end;
		return $this;
	}

	function isFiltered() {
		return $this->start !== null || $this->end !== null;
	}

	/**
	 * @deprecated
	 */
	function start($start) {
		Deprecation::notice('2.0', 'Use setStart()');
		return $this->setStart($start);
	}

	/**
	 * @deprecated
	 */
	function end($end) {
		Deprecation::notice('2.0', 'Use setEnd()');
		return $this->setEnd($end);
	}
}