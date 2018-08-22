<?php

namespace SilverStripe\FullTextSearch\Solr\Writers;

use InvalidArgumentException;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;

/**
 * Class SolrSearchQueryWriter_In
 * @package SilverStripe\FullTextSearch\Solr\Writers
 */
class SolrSearchQueryWriterIn extends AbstractSearchQueryWriter
{
    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $searchCriterion)
    {
        return sprintf(
            '%s%s',
            $this->getComparisonPolarity($searchCriterion->getComparison()),
            $this->getInComparisonString($searchCriterion)
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
        switch ($comparison) {
            case SearchCriterion::NOT_IN:
                return '-';
            default:
                return '+';
        }
    }

    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getInComparisonString(SearchCriterion $searchCriterion)
    {
        $conditions = array();

        if (!is_array($searchCriterion->getValue())) {
            throw new InvalidArgumentException('Invalid value type for Criterion IN');
        }

        foreach ($searchCriterion->getValue() as $value) {
            if (is_string($value)) {
                // String values need to be wrapped in quotes and escaped.
                $value = $searchCriterion->getQuoteValue($value);
            }

            $conditions[] = sprintf(
                '%s%s%s',
                addslashes($searchCriterion->getTarget()),
                $this->getComparisonConjunction(),
                $value
            );
        }

        return sprintf(
            '(%s)',
            implode(' ', $conditions)
        );
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
