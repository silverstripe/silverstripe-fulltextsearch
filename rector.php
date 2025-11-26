<?php

declare(strict_types=1);

use Netwerkstatt\SilverstripeRector\Set\SilverstripeSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    // Force single-threaded execution to prevent worker crash
    ->withParallel(300, 1, 100)

    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withSets([
        SilverstripeSetList::SS_6_0,
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
