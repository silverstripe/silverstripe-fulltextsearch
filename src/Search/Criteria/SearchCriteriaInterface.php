<?php

namespace SilverStripe\FullTextSearch\Search\Criteria;

use SilverStripe\FullTextSearch\Search\Adapters\SearchAdapterInterface;

/**
 * Interface SearchCriteriaInterface
 *
 * SearchCriteria and SearchCriterion objects must implement this interface.
 */
interface SearchCriteriaInterface
{
    /**
     * The method used in all SearchCriterion to generate and append their filter query statements.
     *
     * This is also used in SearchCriteria to loop through it's collected SearchCriterion and append the above. This
     * allows us to have SearchCriteria and SearchCriterion in the same collections (allowing us to have complex nested
     * filtering).
     *
     * @param $ps
     * @return void
     */
    public function appendPreparedStatementTo(&$ps);

    /**
     * @return SearchAdapterInterface
     */
    public function getAdapter();

    /**
     * @param SearchAdapterInterface $adapter
     * @return $this
     */
    public function setAdapter(SearchAdapterInterface $adapter);
}
