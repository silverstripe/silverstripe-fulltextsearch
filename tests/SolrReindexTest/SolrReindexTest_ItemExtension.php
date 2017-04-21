<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Variant;
use SilverStripe\Core\Convert;

/**
 * Select only records in the current variant
 */
class SolrReindexTest_ItemExtension extends DataExtension implements TestOnly
{
    /**
     * Filter records on the current variant
     *
     * @param SQLQuery $query
     * @param DataQuery $dataQuery
     */
    public function augmentSQL(SilverStripe\ORM\Queries\SQLSelect $query, SilverStripe\ORM\DataQuery $dataQuery = NULL)
    {
        $variant = SolrReindexTest_Variant::get_current();
        if ($variant !== null && !$query->filtersOnID()) {
            $sqlVariant = Convert::raw2sql($variant);
            $query->addWhere("\"Variant\" = '{$sqlVariant}'");
        }
    }
}
