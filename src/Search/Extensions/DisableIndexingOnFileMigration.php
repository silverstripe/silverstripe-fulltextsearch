<?php

namespace SilverStripe\FullTextSearch\Search\Extensions;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Extension;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

/**
 * This extension can be applied to `SilverStripe\Dev\Tasks\MigrateFileTask` to avoid constantly re-indexing files
 * while the file migration is running.
 */
class DisableIndexingOnFileMigration extends Extension
{
    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class . '.quiet',
    ];

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function preFileMigration()
    {
        if (SearchUpdater::config()->get('enabled')) {
            $this->logger->info('Disabling fulltext search re-indexing for this request only');
            SearchUpdater::config()->set('enabled', false);
        }
    }
}
