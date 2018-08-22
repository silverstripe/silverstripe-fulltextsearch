<?php

namespace SilverStripe\FullTextSearch\Search\Adapters;

use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;

/**
 * Interface SearchAdapterInterface
 * @package SilverStripe\FullTextSearch\Adapters
 */
interface SearchAdapterInterface
{
    /**
     * Parameter $conjunction should be CONJUNCTION_AND or CONJUNCTION_OR, and your Adapter should return the
     * appropriate string representation of that conjunction.
     *
     * @param string $conjunction
     * @return string
     */
    public function getConjunctionFor($conjunction);

    /**
     * Due to the fact that we have filter criteria coming from legacy methods (as well as our Criteria), you may find
     * that you need to prepend (or append) something to your group of Criteria statements.
     *
     * EG: For Solr, we need to add a "+" between the default filters, and our Criteria.
     *
     * @return string
     */
    public function getPrependToCriteriaComponent();

    /**
     * @return string
     */
    public function getAppendToCriteriaComponent();

    /**
     * Define how each of your comparisons should be contained.
     *
     * EG: For Solr, we wrap each comparison in ().
     *
     * @return string
     */
    public function getOpenComparisonContainer();

    /**
     * @return string
     */
    public function getCloseComparisonContainer();

    /**
     * @param SearchCriterion $criterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $criterion);
}
