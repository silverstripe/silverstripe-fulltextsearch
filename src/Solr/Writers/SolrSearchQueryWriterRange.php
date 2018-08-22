<?php

namespace SilverStripe\FullTextSearch\Solr\Writers;

use InvalidArgumentException;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;

/**
 * Class SolrSearchQueryWriter_Range
 * @package SilverStripe\FullTextSearch\Solr\Writers
 */
class SolrSearchQueryWriterRange extends AbstractSearchQueryWriter
{
    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    public function generateQueryString(SearchCriterion $searchCriterion)
    {
        return sprintf(
            '%s(%s:%s%s%s%s%s)',
            $this->getComparisonPolarity($searchCriterion->getComparison()),
            addslashes($searchCriterion->getTarget()),
            $this->getOpenComparisonContainer($searchCriterion->getComparison()),
            $this->getLeftComparison($searchCriterion),
            $this->getComparisonConjunction(),
            $this->getRightComparison($searchCriterion),
            $this->getCloseComparisonContainer($searchCriterion->getComparison())
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
            case SearchCriterion::ISNULL:
                return '-';
            default:
                return '+';
        }
    }

    /**
     * Select the value that we want as our left comparison value.
     *
     * @param SearchCriterion $searchCriterion
     * @return mixed|string
     * @throws InvalidArgumentException
     */
    protected function getLeftComparison(SearchCriterion $searchCriterion)
    {
        switch ($searchCriterion->getComparison()) {
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::GREATER_THAN:
                return $searchCriterion->getValue();
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::LESS_THAN:
                return '*';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Select the value that we want as our right comparison value.
     *
     * @param SearchCriterion $searchCriterion
     * @return mixed|string
     * @throws InvalidArgumentException
     */
    protected function getRightComparison(SearchCriterion $searchCriterion)
    {
        switch ($searchCriterion->getComparison()) {
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::GREATER_THAN:
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
                return '*';
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::LESS_THAN:
                return $searchCriterion->getValue();
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Decide how we are comparing our left and right values.
     *
     * @return string
     */
    protected function getComparisonConjunction()
    {
        return ' TO ';
    }

    /**
     * Does our comparison need a container? EG: "[* TO *]"? If so, return the opening container brace.
     *
     * @param string $comparison
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getOpenComparisonContainer($comparison)
    {
        switch ($comparison) {
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
                return '[';
            case SearchCriterion::GREATER_THAN:
            case SearchCriterion::LESS_THAN:
                return '{';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }

    /**
     * Does our comparison need a container? EG: "[* TO *]"? If so, return the closing container brace.
     *
     * @param string $comparison
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getCloseComparisonContainer($comparison)
    {
        switch ($comparison) {
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
                return ']';
            case SearchCriterion::GREATER_THAN:
            case SearchCriterion::LESS_THAN:
                return '}';
            default:
                throw new InvalidArgumentException('Invalid comparison for RangeCriterion');
        }
    }
}
