<?php

namespace SilverStripe\FullTextSearch\Search\Queries;

use SilverStripe\Dev\Deprecation;
use SilverStripe\FullTextSearch\Search\Adapters\SearchAdapterInterface;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriteria;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriteriaInterface;
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

    public $search = [];

    public $classes = [];

    public $require = [];
    public $exclude = [];

    /**
     * @var SearchCriteriaInterface[]
     */
    public $criteria = [];

    protected $start = 0;
    protected $limit = -1;

    /**
     * @var SearchAdapterInterface
     */
    protected $adapter = null;

    /** These are the API functions */

    /**
     * SearchQuery constructor.
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
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
     * @param SearchAdapterInterface $adapter
     * @return SearchQuery
     */
    public function setHandler(SearchAdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param string $text   Search terms. Exact format (grouping, boolean expressions, etc.) depends on
     *                       the search implementation.
     * @param array  $fields Limits the search to specific fields (using composite field names)
     * @param array  $boost  Map of composite field names to float values. The higher the value,
     *                       the more important the field gets for relevancy.
     * @return $this
     */
    public function addSearchTerm($text, $fields = null, $boost = [])
    {
        $this->search[] = [
            'text' => $text,
            'fields' => $fields ? (array) $fields : null,
            'boost' => $boost,
            'fuzzy' => false
        ];
        return $this;
    }

    /**
     * Similar to {@link addSearchTerm()}, but uses stemming and other similarity algorithms
     * to find the searched terms. For example, a term "fishing" would also likely find results
     * containing "fish" or "fisher". Depends on search implementation.
     *
     * @param string $text   See {@link addSearchTerm()}
     * @param array  $fields See {@link addSearchTerm()}
     * @param array  $boost  See {@link addSearchTerm()}
     * @return $this
     */
    public function addFuzzySearchTerm($text, $fields = null, $boost = [])
    {
        $this->search[] = [
            'text' => $text,
            'fields' => $fields ? (array) $fields : null,
            'boost' => $boost,
            'fuzzy' => true
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getSearchTerms()
    {
        return $this->search;
    }

    /**
     * @param string $class
     * @param bool $includeSubclasses
     * @return $this
     */
    public function addClassFilter($class, $includeSubclasses = true)
    {
        $this->classes[] = [
            'class' => $class,
            'includeSubclasses' => $includeSubclasses
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getClassFilters()
    {
        return $this->classes;
    }

    /**
     * Similar to {@link addSearchTerm()}, but typically used to further narrow down
     * based on other facets which don't influence the field relevancy.
     *
     * @param string $field  Composite name of the field
     * @param mixed  $values Scalar value, array of values, or an instance of SearchQuery_Range
     * @return $this
     */
    public function addFilter($field, $values)
    {
        $requires = isset($this->require[$field]) ? $this->require[$field] : [];
        $values = is_array($values) ? $values : [$values];
        $this->require[$field] = array_merge($requires, $values);
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->require;
    }

    /**
     * Excludes results which match these criteria, inverse of {@link addFilter()}.
     *
     * @param string $field
     * @param mixed $values
     * @return $this
     */
    public function addExclude($field, $values)
    {
        $excludes = isset($this->exclude[$field]) ? $this->exclude[$field] : [];
        $values = is_array($values) ? $values : [$values];
        $this->exclude[$field] = array_merge($excludes, $values);
        return $this;
    }

    /**
     * @return array
     */
    public function getExcludes()
    {
        return $this->exclude;
    }

    /**
     * You can pass through a string value, Criteria object, or Criterion object for $target.
     *
     * String value might be "SiteTree_Title" or whatever field in your index that you're trying to target.
     *
     * If you require complex filtering then you can build your Criteria object first with multiple layers/levels of
     * Criteria, and then pass it in here when you're ready.
     *
     * If you have your own Criterion object that you've created that you want to use, you can also pass that in here.
     *
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return SearchCriteriaInterface
     */
    public function filterBy(
        $target,
        $value = null,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        if (!$target instanceof SearchCriteriaInterface) {
            $target = new SearchCriteria($target, $value, $comparison, $searchQueryWriter);
        }

        $this->addCriteria($target);

        return $target;
    }

    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    public function setPageSize($page)
    {
        $this->setStart($page * self::$default_page_size);
        $this->setLimit(self::$default_page_size);
        return $this;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return (int) ($this->getLimit() / $this->getStart());
    }

    /**
     * @return bool
     */
    public function isFiltered()
    {
        return $this->search || $this->classes || $this->require || $this->exclude;
    }

    /**
     * @return SearchAdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    public function __toString()
    {
        return "Search Query\n";
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function search($text, $fields = null, $boost = [])
    {
        Deprecation::notice('4.0', 'Use addSearchTerm() instead');
        return $this->addSearchTerm($text, $fields, $boost);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function fuzzysearch($text, $fields = null, $boost = [])
    {
        Deprecation::notice('4.0', 'Use addFuzzySearchTerm() instead');
        return $this->addFuzzySearchTerm($text, $fields, $boost);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function inClass($class, $includeSubclasses = true)
    {
        Deprecation::notice('4.0', 'Use addClassFilter() instead');
        return $this->addClassFilter($class, $includeSubclasses);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function filter($field, $values)
    {
        Deprecation::notice('4.0', 'Use addFilter() instead');
        return $this->addFilter($field, $values);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function exclude($field, $values)
    {
        Deprecation::notice('4.0', 'Use addExclude() instead');
        return $this->addExclude($field, $values);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function start($start)
    {
        Deprecation::notice('4.0', 'Use setStart() instead');
        return $this->setStart($start);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function limit($limit)
    {
        Deprecation::notice('4.0', 'Use setLimit() instead');
        return $this->setLimit($limit);
    }

    /**
     * @codeCoverageIgnore
     * @deprecated
     */
    public function page($page)
    {
        Deprecation::notice('4.0', 'Use setPageSize() instead');
        return $this->setPageSize($page);
    }

    /**
     * @return SearchCriteriaInterface[]
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @param SearchCriteriaInterface[] $criteria
     * @return SearchQuery
     */
    public function setCriteria($criteria)
    {
        $this->criteria = $criteria;
        return $this;
    }

    /**
     * @param SearchCriteriaInterface $criteria
     * @return SearchQuery
     */
    public function addCriteria($criteria)
    {
        $this->criteria[] = $criteria;
        return $this;
    }
}
