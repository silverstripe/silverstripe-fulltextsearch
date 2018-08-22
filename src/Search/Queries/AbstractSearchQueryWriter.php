<?php

namespace SilverStripe\FullTextSearch\Search\Queries;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;

/**
 * Class AbstractSearchQueryWriter
 * @package SilverStripe\FullTextSearch\Search\Queries
 */
abstract class AbstractSearchQueryWriter
{
    use Injectable;

    /**
     * @param SearchCriterion $searchCriterion
     * @return string
     */
    abstract public function generateQueryString(SearchCriterion $searchCriterion);
}
