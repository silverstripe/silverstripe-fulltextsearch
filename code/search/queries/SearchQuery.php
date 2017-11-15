<?php

namespace SilverStripe\FullTextSearch\Search\Queries;

use SilverStripe\View\ViewableData;
use stdClass;

/**
 * Represents a search query
 *
 * API very much still in flux.
 */
class SearchQuery extends ViewableData
{
    public static $missing = null;
    public static $present = null;

    public static $default_page_size = 10;

    /** These are public, but only for index & variant access - API users should not manually access these */

    public $search = array();

    public $classes = array();

    public $require = array();
    public $exclude = array();

    protected $start = 0;
    protected $limit = -1;

    /** These are the API functions */

    public function __construct()
    {
        if (self::$missing === null) {
            self::$missing = new stdClass();
        }
        if (self::$present === null) {
            self::$present = new stdClass();
        }
    }

    /**
     * @param  String $text Search terms. Exact format (grouping, boolean expressions, etc.) depends on the search implementation.
     * @param  array $fields Limits the search to specific fields (using composite field names)
     * @param  array  $boost  Map of composite field names to float values. The higher the value,
     * the more important the field gets for relevancy.
     */
    public function search($text, $fields = null, $boost = array())
    {
        $this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => false);
    }

    /**
     * Similar to {@link search()}, but uses stemming and other similarity algorithms
     * to find the searched terms. For example, a term "fishing" would also likely find results
     * containing "fish" or "fisher". Depends on search implementation.
     *
     * @param  String $text See {@link search()}
     * @param  array $fields See {@link search()}
     * @param  array $boost See {@link search()}
     */
    public function fuzzysearch($text, $fields = null, $boost = array())
    {
        $this->search[] = array('text' => $text, 'fields' => $fields ? (array)$fields : null, 'boost' => $boost, 'fuzzy' => true);
    }

    public function inClass($class, $includeSubclasses = true)
    {
        $this->classes[] = array('class' => $class, 'includeSubclasses' => $includeSubclasses);
    }

    /**
     * Similar to {@link search()}, but typically used to further narrow down
     * based on other facets which don't influence the field relevancy.
     *
     * @param  String $field Composite name of the field
     * @param  Mixed $values Scalar value, array of values, or an instance of SearchQuery_Range
     */
    public function filter($field, $values)
    {
        $requires = isset($this->require[$field]) ? $this->require[$field] : array();
        $values = is_array($values) ? $values : array($values);
        $this->require[$field] = array_merge($requires, $values);
    }

    /**
     * Excludes results which match these criteria, inverse of {@link filter()}.
     *
     * @param  String $field
     * @param  mixed $values
     */
    public function exclude($field, $values)
    {
        $excludes = isset($this->exclude[$field]) ? $this->exclude[$field] : array();
        $values = is_array($values) ? $values : array($values);
        $this->exclude[$field] = array_merge($excludes, $values);
    }

    public function start($start)
    {
        $this->start = $start;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
    }

    public function page($page)
    {
        $this->start = $page * self::$default_page_size;
        $this->limit = self::$default_page_size;
    }

    public function isfiltered()
    {
        return $this->search || $this->classes || $this->require || $this->exclude;
    }

    public function __toString()
    {
        return "Search Query\n";
    }
}
