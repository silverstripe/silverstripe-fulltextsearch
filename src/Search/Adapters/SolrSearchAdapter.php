<?php

namespace SilverStripe\FullTextSearch\Search\Adapters;

use SilverStripe\FullTextSearch\Search\Criteria\SearchCriteria;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterBasic;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterIn;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterRange;
use InvalidArgumentException;

/**
 * Class SolrSearchAdapter
 * @package SilverStripe\FullTextSearch\Search\Adapters
 */
class SolrSearchAdapter implements SearchAdapterInterface
{
    /**
     * @param SearchCriterion $criterion
     * @return string
     * @throws InvalidArgumentException
     */
    public function generateQueryString(SearchCriterion $criterion)
    {
        $writer = $this->getSearchQueryWriter($criterion);

        return $writer->generateQueryString($criterion);
    }

    /**
     * @param string $conjunction
     * @return string
     * @throws InvalidArgumentException
     */
    public function getConjunctionFor($conjunction)
    {
        switch ($conjunction) {
            case SearchCriteria::CONJUNCTION_AND:
            case SearchCriteria::CONJUNCTION_OR:
                return sprintf(' %s ', $conjunction);
            default:
                throw new InvalidArgumentException(
                    sprintf('Invalid conjunction supplied to SolrSearchAdapter: "%s".', $conjunction)
                );
        }
    }

    /**
     * @return string
     */
    public function getPrependToCriteriaComponent()
    {
        return '+';
    }

    /**
     * @return string
     */
    public function getAppendToCriteriaComponent()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getOpenComparisonContainer()
    {
        return '(';
    }

    /**
     * @return string
     */
    public function getCloseComparisonContainer()
    {
        return ')';
    }

    /**
     * @param SearchCriterion $searchCriterion
     * @return AbstractSearchQueryWriter
     * @throws InvalidArgumentException
     */
    protected function getSearchQueryWriter(SearchCriterion $searchCriterion)
    {
        if ($searchCriterion->getSearchQueryWriter() instanceof AbstractSearchQueryWriter) {
            // The user has defined their own SearchQueryWriter, so we should just return it.
            return $searchCriterion->getSearchQueryWriter();
        }

        switch ($searchCriterion->getComparison()) {
            case SearchCriterion::EQUAL:
            case SearchCriterion::NOT_EQUAL:
                return SolrSearchQueryWriterBasic::create();
            case SearchCriterion::IN:
            case SearchCriterion::NOT_IN:
                return SolrSearchQueryWriterIn::create();
            case SearchCriterion::GREATER_EQUAL:
            case SearchCriterion::GREATER_THAN:
            case SearchCriterion::LESS_EQUAL:
            case SearchCriterion::LESS_THAN:
            case SearchCriterion::ISNULL:
            case SearchCriterion::ISNOTNULL:
                return SolrSearchQueryWriterRange::create();
            case SearchCriterion::CUSTOM:
                // CUSTOM requires a SearchQueryWriter be provided. One can't have been provided, or it would have been
                // picked up at the top of the method.
                throw new InvalidArgumentException('SearchQueryWriter undefined or unsupported in SearchCriterion');
            default:
                throw new InvalidArgumentException('Unsupported comparison type in SolrSearchAdapter');
        }
    }
}
