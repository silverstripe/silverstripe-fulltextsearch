<?php

namespace SilverStripe\FullTextSearch\Solr\Reindex\Handlers;

use InvalidArgumentException;
use LogicException;
use Override;
use ReflectionClass;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Invokes an immediate reindex
 *
 * Each batch of records is run in the current process. Note that this means the memory used by a
 * full reindex accumulates across all groups - prefer the queued handler for large data sets.
 */
class SolrReindexImmediateHandler extends SolrReindexBase
{
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
     * Process a single group by running the reindex task for that group in the current process.
     *
     * The task will invoke $processor->runGroup() in {@see Solr_Reindex::doReindex}
     *
     * @param PolyOutput $logger
     * @param SolrIndex $indexInstance Index instance
     * @param array $state Variant state
     * @param string $class Class to index
     * @param int $groups Total groups
     * @param int $group Index of group to process
     * @param string $taskName Name of the task to run
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
        $taskClass = $this->resolveTaskClass($taskName);

        $params = [
            '--index' => $indexClass,
            '--class' => $class,
            '--group' => $group,
            '--groups' => $groups,
            '--variantstate' => json_encode($state),
            '--verbose' => true,
        ];

        $logger->writeln('Running ' . $this->buildCommandLine($taskClass, $params));

        $res = $this->executeBuiltTask($taskClass, $params, true);
        $logger->writeln(preg_replace('/\r\n|\n/', '$0  ', $res ?? ''));

        // If we're in dev mode, commit more often for fun and profit
        if (Director::isDev()) {
            Solr::service($indexClass)->commit();
        }

        // This will slow down things a tiny bit, but it is done so that we don't timeout to the database during a reindex
        DB::query('SELECT 1');
    }

    /**
     * Run a build task in the current process
     *
     * @param string $taskName Class name of the task, or the name it is registered under on the CLI
     * @param array $params Options to pass to the task, keyed by option name (including the `--` prefix)
     * @param bool $returnOutput Whether to return everything the task wrote to its output
     */
    public function executeBuiltTask(string $taskName, array $params = [], bool $returnOutput = false): ?string
    {
        $taskClass = $this->resolveTaskClass($taskName);

        /** @var BuildTask $task */
        $task = $taskClass::create();

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

    /**
     * Resolve a task to its class name.
     *
     * Accepts a class name, or the name the task is registered under on the CLI (with or without
     * the `tasks:` prefix), or its unqualified class name - reindex jobs queued before this module
     * was upgraded hold the latter in their stored job data.
     *
     * @throws InvalidArgumentException if the task cannot be resolved
     */
    public function resolveTaskClass(string $taskName): string
    {
        if (is_a($taskName, BuildTask::class, true)) {
            return $taskName;
        }

        foreach (ClassInfo::subclassesFor(BuildTask::class, false) as $candidate) {
            if (!(new ReflectionClass($candidate))->isInstantiable()) {
                continue;
            }

            if (in_array($taskName, $this->getTaskNames($candidate), true)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException("Unable to resolve '{$taskName}' to a build task");
    }

    /**
     * All of the names a given task can be referred to by.
     *
     * BuildTask::getNameWithoutNamespace() throws if the task declares a commandName containing
     * `:` or `/`. Such a task can't be registered on the CLI in the first place, so skip over it
     * rather than let it break resolution of every other task.
     *
     * @param string $taskClass
     * @return string[]
     */
    private function getTaskNames(string $taskClass): array
    {
        try {
            return [
                $taskClass::getName(),
                $taskClass::getNameWithoutNamespace(),
                ClassInfo::shortName($taskClass),
            ];
        } catch (LogicException) {
            return [ClassInfo::shortName($taskClass)];
        }
    }

    /**
     * Build the command someone would run to invoke this task themselves. The task is run in the
     * current process, so this is only ever written to the output - values are escaped so that the
     * command can be pasted into a shell as-is.
     */
    protected function buildCommandLine(string $taskClass, array $params): string
    {
        $parts = ['sake', $taskClass::getName()];

        foreach ($params as $option => $value) {
            $parts[] = is_bool($value) ? $option : $option . '=' . escapeshellarg((string) $value);
        }

        return implode(' ', $parts);
    }
}
