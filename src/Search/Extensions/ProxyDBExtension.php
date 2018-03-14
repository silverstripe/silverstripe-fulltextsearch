<?php

namespace SilverStripe\FullTextSearch\Search\Extensions;

use SilverStripe\Core\Extension;
use TractorCow\ClassProxy\Generators\ProxyGenerator;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * This database connector proxy will allow {@link SearchUpdater::handle_manipulation} to monitor database schema
 * changes that may need to be propagated through to search indexes.
 *
 */
class ProxyDBExtension extends Extension
{
    /**
     * @param ProxyGenerator $proxy
     *
     * Ensure the search index is kept up to date by monitoring SilverStripe database manipulations
     */
    public function updateProxy(ProxyGenerator &$proxy)
    {
        $proxy = $proxy->addMethod('manipulate', function ($args, $next) {
            $manipulation = $args[0];
            SearchUpdater::handle_manipulation($manipulation);
            return $next(...$args);
        });
    }
}
