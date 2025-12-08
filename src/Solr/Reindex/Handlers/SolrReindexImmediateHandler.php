<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Handlers;

use Override;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use SilverStripe\Core\Config\Configurable;

/**
 * Invokes an immediate reindex
 *
 * Internally batches of records will be invoked via shell tasks in the background
 */
class SolrReindexImmediateHandler extends SolrReindexBase
{

    use Configurable;

    /**
     * Path to the php binary
     * @config
     * @var null|string
     */
    private static $php_bin = 'php';


    public function triggerReindex(PolyOutput $logger, $batchSize, $taskName, $classes = null)
    {
        $this->runReindex($logger, $batchSize, $taskName, $classes);
    }

    #[Override]
    protected function processIndex(
        PolyOutput $logger,
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
        PolyOutput $logger,
        SolrIndex $indexInstance,
        $state,
        $class,
        $groups,
        $group,
        $taskName
    ) {
        $indexClass = $indexInstance::class;

        // Build script parameters
        $statevar = json_encode($state);

        $php = Environment::getEnv('SS_PHP_BIN') ?: Config::inst()->get(static::class, 'php_bin');

        // Build script line
        $frameworkPath = ModuleLoader::getModule('silverstripe/framework')->getPath();
        $scriptPath = sprintf("%s%scli-script.php", $frameworkPath, DIRECTORY_SEPARATOR);

        $cmd = [
            'sake',
            "tasks:{$taskName}",
            "--index={$indexClass}",
            "--class={$class}",
            "--group={$group}",
            "--groups={$groups}",
            "--variantstate={$statevar}",
            "--verbose=1"
        ];
        $logger->writeln('Running ' . implode(' ', $cmd));

        // Execute script
        $res = $this->executeBuiltTask(
            $taskName,
            [
                '--index' => $indexClass,
                '--class' => $class,
                '--group' => $group,
                '--groups' => $groups,
                '--variantstate' => $statevar,
                '--verbose' => true,
            ],
            true
        );
        if ($logger) {
            $logger->writeln(preg_replace('/\r\n|\n/', '$0  ', $res ?? ''));
        }

        // If we're in dev mode, commit more often for fun and profit
        if (Director::isDev()) {
            Solr::service($indexClass)->commit();
        }

        // This will slow down things a tiny bit, but it is done so that we don't timeout to the database during a reindex
        DB::query('SELECT 1');
    }

    public function executeBuiltTask(string $className, array $params = [], bool $returnOutput = false): ?string
    {
        $definition = [];
        $paramNames = array_keys($params);

        $task = $className::create();

        $options = $task->getOptions();
        $options[] = new InputOption('verbose', null, InputOption::VALUE_NONE, 'verbose');

        $input = new ArrayInput($params, new InputDefinition($options));
        $input->setInteractive(false);
        $buffer = new BufferedOutput();
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer);
        $task->run($input, $output);

        if ($returnOutput) {
            return $buffer->fetch();
        }

        return null;
    }

}
