<?php

namespace SilverStripe\FullTextSearch\Search\Indexes;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;

/**
 * A search index that does nothing. Useful for testing
 */
abstract class SearchIndex_Null extends SearchIndex
{
    public function add($object)
    {
    }

    public function delete($base, $id, $state)
    {
    }

    public function commit()
    {
    }
}
