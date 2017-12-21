<?php

namespace SilverStripe\FullTextSearch\State;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class FullTextSearchTestState implements TestState
{
    public function setUp(SapphireTest $test)
    {
        // noop
    }

    public function tearDown(SapphireTest $test)
    {
        SearchVariant::clear_variant_cache();
    }

    public function setUpOnce($class)
    {
        Config::modify()->set(SearchUpdater::class, 'flush_on_shutdown', false);

        if (class_exists(QueuedJobService::class)) {
            Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
        }
    }

    public function tearDownOnce($class)
    {
        // noop
    }
}
