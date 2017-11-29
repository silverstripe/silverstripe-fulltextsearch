<?php

namespace SilverStripe\FullTextSearch\Search\Queries;

/**
 * Create one of these and pass as one of the values in filter or exclude to filter or exclude by a (possibly
 * open ended) range
 */
class SearchQuery_Range
{
    public $start = null;
    public $end = null;

    public function __construct($start = null, $end = null)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function start($start)
    {
        $this->start = $start;
    }

    public function end($end)
    {
        $this->end = $end;
    }

    public function isfiltered()
    {
        return $this->start !== null || $this->end !== null;
    }
}
