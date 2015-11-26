<?php

use Psr\Log\LoggerInterface;

if (!class_exists('MessageQueue')) {
    return;
}

class SolrReindexMessageHandler extends SolrReindexImmediateHandler
{
    /**
     * The MessageQueue to use when processing updates
     * @config
     * @var string
     */
    private static $reindex_queue = "search_indexing";

    public function triggerReindex(LoggerInterface $logger, $batchSize, $taskName, $classes = null)
    {
        $queue = Config::inst()->get(__CLASS__, 'reindex_queue');

        $logger->info('Queuing message');
        MessageQueue::send(
            $queue,
            new MethodInvocationMessage('SolrReindexMessageHandler', 'run_reindex', $batchSize, $taskName, $classes)
        );
    }

    /**
     * Entry point for message queue
     *
     * @param int $batchSize
     * @param string $taskName
     * @param array|string|null $classes
     */
    public static function run_reindex($batchSize, $taskName, $classes = null)
    {
        // @todo Logger for message queue?
        $logger = Injector::inst()->createWithArgs('Monolog\Logger', array(strtolower(get_class())));
        
        $inst = Injector::inst()->get(get_class());
        $inst->runReindex($logger, $batchSize, $taskName, $classes);
    }
}
