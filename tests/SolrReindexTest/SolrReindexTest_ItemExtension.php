<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DataQuery;
use SilverStripe\FullTextSearch\Tests\SolrReindexTest\SolrReindexTest_Variant;
use SilverStripe\Core\Convert;

/**
 * Select only records in the current variant
 */
class SolrReindexTest_ItemExtension extends Extension implements TestOnly
{
    /**
     * Filter records on the current variant
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentSQL(SQLSelect $query, ?DataQuery $dataQuery = null)
    {
        $variant = SolrReindexTest_Variant::get_current();
        if ($variant !== null && !$query->filtersOnID()) {
            $sqlVariant = Convert::raw2sql($variant);
            $query->addWhere("\"Variant\" = '{$sqlVariant}'");
        }
    }
}
