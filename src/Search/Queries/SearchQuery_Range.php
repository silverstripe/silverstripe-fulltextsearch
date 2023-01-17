<?php

namespace SilverStripe\FullTextSearch\Search\Queries;

use SilverStripe\Core\Injector\Injectable;

/**
 * Create one of these and pass as one of the values in filter or exclude to filter or exclude by a (possibly
 * open ended) range
 */
class SearchQuery_Range
{
    use Injectable;
    
    public $start = null;
    public $end = null;

    public function __construct($start = null, $end = null)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    public function setEnd($end)
    {
        $this->end = $end;
        return $this;
    }

    public function isFiltered()
    {
        return $this->start !== null || $this->end !== null;
    }
}
