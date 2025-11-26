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
            addslashes($searchCriterion->getTarget() ?? ''),
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
        return match ($comparison) {
            SearchCriterion::ISNULL => '-',
            default => '+',
        };
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
        return match ($searchCriterion->getComparison()) {
            SearchCriterion::GREATER_EQUAL, SearchCriterion::GREATER_THAN => $searchCriterion->getValue(),
            SearchCriterion::ISNULL, SearchCriterion::ISNOTNULL, SearchCriterion::LESS_EQUAL, SearchCriterion::LESS_THAN => '*',
            default => throw new InvalidArgumentException('Invalid comparison for RangeCriterion'),
        };
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
        return match ($searchCriterion->getComparison()) {
            SearchCriterion::GREATER_EQUAL, SearchCriterion::GREATER_THAN, SearchCriterion::ISNULL, SearchCriterion::ISNOTNULL => '*',
            SearchCriterion::LESS_EQUAL, SearchCriterion::LESS_THAN => $searchCriterion->getValue(),
            default => throw new InvalidArgumentException('Invalid comparison for RangeCriterion'),
        };
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
        return match ($comparison) {
            SearchCriterion::GREATER_EQUAL, SearchCriterion::LESS_EQUAL, SearchCriterion::ISNULL, SearchCriterion::ISNOTNULL => '[',
            SearchCriterion::GREATER_THAN, SearchCriterion::LESS_THAN => '{',
            default => throw new InvalidArgumentException('Invalid comparison for RangeCriterion'),
        };
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
        return match ($comparison) {
            SearchCriterion::GREATER_EQUAL, SearchCriterion::LESS_EQUAL, SearchCriterion::ISNULL, SearchCriterion::ISNOTNULL => ']',
            SearchCriterion::GREATER_THAN, SearchCriterion::LESS_THAN => '}',
            default => throw new InvalidArgumentException('Invalid comparison for RangeCriterion'),
        };
    }
}
