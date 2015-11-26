<?php

class SearchUpdateMessageQueueProcessor extends SearchUpdateProcessor
{
    /**
     * The MessageQueue to use when processing updates
     * @config
     * @var string
     */
    private static $reindex_queue = "search_indexing";

    public function triggerProcessing()
    {
        MessageQueue::send(
            Config::inst()->get('SearchMessageQueueUpdater', 'reindex_queue'),
            new MethodInvocationMessage($this, "process")
        );
    }
}
