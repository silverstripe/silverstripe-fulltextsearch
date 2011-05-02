<?php

/**
 * Represents a search query
 *
 * API very much still in flux. Generally, calling with multiple arguments = OR, calling multiple times = AND.
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

	/** These are the API functions */

	function __construct() {
		if (self::$missing === null) self::$missing = new stdClass();
		if (self::$present === null) self::$present = new stdClass();
	}

	function search($text, $fields = null, $boost = 1) {
		$this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => false);
	}

	function fuzzysearch($text, $fields = null, $boost = 1) {
		$this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => true);
	}

	function inClass($class, $includeSubclasses = true) {
		$this->classes[] = array('class' => $class, 'includeSubclasses' => $includeSubclasses);
	}

	function filter($field, $values) {
		$requires = isset($this->require[$field]) ? $this->require[$field] : array();
		$values = is_array($values) ? $values : array($values);
		$this->require[$field] = array_merge($requires, $values);
	}

	function exclude($field, $values) {
		$excludes = isset($this->exclude[$field]) ? $this->exclude[$field] : array();
		$values = is_array($values) ? $values : array($values);
		$this->exclude[$field] = array_merge($excludes, $values);
	}

	function start($start) {
		$this->start = $start;
	}

	function limit($limit) {
		$this->limit = $limit;
	}

	function page($page) {
		$this->start = $page * self::$default_page_size;
		$this->limit = self::$default_page_size;
	}

	function isfiltered() {
		return $this->search || $this->classes || $this->require || $this->exclude;
	}

	function __toString() {
		return "Search Query\n";
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

	function start($start) {
		$this->start = $start;
	}

	function end($end) {
		$this->end = $end;
	}

	function isfiltered() {
		return $this->start !== null || $this->end !== null;
	}
}