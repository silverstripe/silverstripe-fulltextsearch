<?php

namespace SilverStripe\FullTextSearch\Search\Processors;

class SearchUpdateImmediateProcessor extends SearchUpdateProcessor
{
    public function triggerProcessing()
    {
        $this->process();
    }
}
