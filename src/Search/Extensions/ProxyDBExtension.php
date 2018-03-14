<?php

namespace SilverStripe\FullTextSearch\Search\Extensions;

use SilverStripe\Core\Extension;
use TractorCow\ClassProxy\Generators\ProxyGenerator;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

class ProxyDBExtension extends Extension
{
    public function updateProxy(ProxyGenerator &$proxy)
    {
        $proxy = $proxy->addMethod('manipulate', function ($args, $next) {
            $manipulation = $args[0];
            SearchUpdater::handle_manipulation($manipulation);
            return $next(...$args);
        });
    }
}
