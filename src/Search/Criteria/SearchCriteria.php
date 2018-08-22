<?php

namespace SilverStripe\FullTextSearch\Search\Criteria;

use SilverStripe\FullTextSearch\Search\Adapters\SearchAdapterInterface;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;

/**
 * Class SearchCriteria
 * @package SilverStripe\FullTextSearch\Criteria
 */
class SearchCriteria implements SearchCriteriaInterface
{
    /**
     * @param string
     */
    const CONJUNCTION_AND = 'AND';

    /**
     * @param string
     */
    const CONJUNCTION_OR = 'OR';

    /**
     * A collection of SearchCriterion and SearchCriteria.
     *
     * @var SearchCriteriaInterface[]
     */
    protected $clauses = array();

    /**
     * The conjunctions used between Criteria (AND/OR).
     *
     * @var string[]
     */
    protected $conjunctions = array();

    /**
     * @var SearchAdapterInterface|null
     */
    protected $adapter = null;

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
     * @param string|SearchCriterion $target
     * @param mixed $value
     * @param string|null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     */
    public function __construct(
        $target,
        $value = null,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        $this->addClause($this->getCriterionForCondition($target, $value, $comparison, $searchQueryWriter));
    }

    /**
     * Static create method provided so that you can perform method chaining.
     *
     * @param $target
     * @param null $value
     * @param null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return SearchCriteria
     */
    public static function create(
        $target,
        $value = null,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        return new SearchCriteria($target, $value, $comparison, $searchQueryWriter);
    }

    /**
     * @return null|SearchAdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param SearchAdapterInterface $adapter
     * @return $this
     */
    public function setAdapter(SearchAdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param string $ps Current prepared statement.
     * @return void
     * @throws \Exception
     */
    public function appendPreparedStatementTo(&$ps)
    {
        $adapter = $this->getAdapter();

        if (!$adapter instanceof SearchAdapterInterface) {
            throw new \Exception('No adapter has been applied to SearchCriteria');
        }

        $ps .= $adapter->getOpenComparisonContainer();

        foreach ($this->getClauses() as $key => $clause) {
            $clause->setAdapter($adapter);
            $clause->appendPreparedStatementTo($ps);

            // There's always one less conjunction then there are clauses.
            if ($this->getConjunction($key) !== null) {
                $ps .= $adapter->getConjunctionFor($this->getConjunction($key));
            }
        }

        $ps .= $adapter->getCloseComparisonContainer();
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return $this
     */
    public function addAnd(
        $target,
        $value = null,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        $criterion = $this->getCriterionForCondition($target, $value, $comparison, $searchQueryWriter);

        $this->addConjunction(SearchCriteria::CONJUNCTION_AND);
        $this->addClause($criterion);

        return $this;
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string|null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return $this
     */
    public function addOr(
        $target,
        $value = null,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        $criterion = $this->getCriterionForCondition($target, $value, $comparison, $searchQueryWriter);

        $this->addConjunction(SearchCriteria::CONJUNCTION_OR);
        $this->addClause($criterion);

        return $this;
    }

    /**
     * @param string|SearchCriteriaInterface $target
     * @param mixed $value
     * @param string $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return SearchCriteriaInterface
     */
    protected function getCriterionForCondition(
        $target,
        $value,
        $comparison,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        if ($target instanceof SearchCriteriaInterface) {
            return $target;
        }

        return new SearchCriterion($target, $value, $comparison, $searchQueryWriter);
    }

    /**
     * @return SearchCriteriaInterface[]
     */
    protected function getClauses()
    {
        return $this->clauses;
    }

    /**
     * @param SearchCriteriaInterface $criterion
     */
    protected function addClause($criterion)
    {
        $this->clauses[] = $criterion;
    }

    /**
     * @return string[]
     */
    protected function getConjunctions()
    {
        return $this->conjunctions;
    }

    /**
     * @param int $key
     * @return string|null
     */
    protected function getConjunction($key)
    {
        $conjunctions = $this->getConjunctions();
        if (!array_key_exists($key, $conjunctions)) {
            return null;
        }

        return $conjunctions[$key];
    }

    /**
     * @param string $conjunction
     */
    protected function addConjunction($conjunction)
    {
        $this->conjunctions[] = $conjunction;
    }
}
