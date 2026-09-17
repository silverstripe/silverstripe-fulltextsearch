<?php

namespace SilverStripe\FullTextSearch\Tests\SolrTasksTest;

use RuntimeException;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TestOnly;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class SolrTasksTest_ContextTask extends BuildTask implements TestOnly
{
    protected string $title = 'Controller context test task';

    protected static string $commandName = 'solr-tasks-context-test';

    /**
     * Records the actual controller visible inside executeBuiltTask()
     */
    public static ?Controller $observedController = null;

    /**
     * Records whether its request had a session
     */
    public static bool $observedSession = false;

    public static bool $throwException = false;

    /**
     * Prevents static state leaking between tests
     */
    public static function reset(): void
    {
        self::$observedController = null;
        self::$observedSession = false;
        self::$throwException = false;
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        self::$observedController = Controller::curr();
        self::$observedSession = self::$observedController && self::$observedController->getRequest()->hasSession();

        if (self::$throwException) {
            throw new RuntimeException('Context task exception');
        }

        return Command::SUCCESS;
    }
}
