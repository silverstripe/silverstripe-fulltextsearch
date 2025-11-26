<?php

namespace SilverStripe\FullTextSearch\Solr\Writers;

use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;

/**
 * Class SolrSearchQueryWriter_Basic
 * @package SilverStripe\FullTextSearch\Solr\Writers
 */
class SolrSearchQueryWriterBasic extends AbstractSearchQueryWriter
{
    /**
     * @var SearchCriterion $searchCriterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $searchCriterion)
    {
        return sprintf(
            '%s(%s%s%s)',
            $this->getComparisonPolarity($searchCriterion->getComparison()),
            addslashes($searchCriterion->getTarget() ?? ''),
            $this->getComparisonConjunction(),
            $searchCriterion->getQuoteValue($searchCriterion->getValue())
        );
    }

    /**
     * Is this a positive (+) or negative (-) Solr comparison.
     *
     * @param string $comparison
     * @return string
     */
    protected function getComparisonPolarity($comparison)
    {
        return match ($comparison) {
            SearchCriterion::NOT_EQUAL => '-',
            default => '+',
        };
    }

    /**
     * Decide how we are comparing our left and right values.
     *
     * @return string
     */
    protected function getComparisonConjunction()
    {
        return ':';
    }
}
