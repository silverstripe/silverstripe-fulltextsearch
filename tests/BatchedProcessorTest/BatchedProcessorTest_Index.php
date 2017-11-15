<?php

namespace SilverStripe\FullTextSearch\Tests\BatchedProcessorTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;

class BatchedProcessorTest_Index extends SearchIndex_Recording implements TestOnly
{
    public function init()
    {
        $this->addClass(BatchedProcessorTest_Object::class);
        $this->addFilterField('TestText');
    }
}
