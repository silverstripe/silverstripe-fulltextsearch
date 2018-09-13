<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Handlers;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\ORM\DB;
use Symfony\Component\Process\Process;

/**
 * Invokes an immediate reindex
 *
 * Internally batches of records will be invoked via shell tasks in the background
 */
class SolrReindexImmediateHandler extends SolrReindexBase
{
    /**
     * Path to the php binary
     * @config
     * @var null|string
     */
    private static $php_bin = 'php';


    public function triggerReindex(LoggerInterface $logger, $batchSize, $taskName, $classes = null)
    {
        $this->runReindex($logger, $batchSize, $taskName, $classes);
    }

    protected function processIndex(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $batchSize,
        $taskName,
        $classes = null
    ) {
        parent::processIndex($logger, $indexInstance, $batchSize, $taskName, $classes);

        // Immediate processor needs to immediately commit after each index
        $indexInstance->getService()->commit();
    }

    /**
     * Process a single group.
     *
     * Without queuedjobs, it's necessary to shell this out to a background task as this is
     * very memory intensive.
     *
     * The sub-process will then invoke $processor->runGroup() in {@see Solr_Reindex::doReindex}
     *
     * @param LoggerInterface $logger
     * @param SolrIndex $indexInstance Index instance
     * @param array $state Variant state
     * @param string $class Class to index
     * @param int $groups Total groups
     * @param int $group Index of group to process
     * @param string $taskName Name of task script to run
     */
    protected function processGroup(
        LoggerInterface $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group,
        $taskName
    ) {
        $indexClass = get_class($indexInstance);

        // Build script parameters
        $indexClassEscaped = $indexClass;
        $statevar = json_encode($state);

        if (strpos(PHP_OS, "WIN") !== false) {
            $statevar = '"' . str_replace('"', '\\"', $statevar) . '"';
        } else {
            $statevar = "'" . $statevar . "'";
            $class = addslashes($class);
            $indexClassEscaped = addslashes($indexClass);
        }

        $php = Environment::getEnv('SS_PHP_BIN') ?: Config::inst()->get(static::class, 'php_bin');

        // Build script line
        $frameworkPath = ModuleLoader::getModule('silverstripe/framework')->getPath();
        $scriptPath = sprintf("%s%scli-script.php", $frameworkPath, DIRECTORY_SEPARATOR);
        $scriptTask = "{$php} {$scriptPath} dev/tasks/{$taskName}";

        $cmd = "{$scriptTask} index={$indexClassEscaped} class={$class} group={$group} groups={$groups} variantstate={$statevar}";
        $cmd .= " verbose=1";
        $logger->info("Running '$cmd'");

        // Execute script via shell
        $process = new Process($cmd);
        $process->inheritEnvironmentVariables();
        $process->run();

        $res = $process->getOutput();
        if ($logger) {
            $logger->info(preg_replace('/\r\n|\n/', '$0  ', $res));
        }

        // If we're in dev mode, commit more often for fun and profit
        if (Director::isDev()) {
            Solr::service($indexClass)->commit();
        }

        // This will slow down things a tiny bit, but it is done so that we don't timeout to the database during a reindex
        DB::query('SELECT 1');
    }
}
