<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test output for recording messages written during a reindex
 */
class SolrReindexTest_RecordingLogger extends PolyOutput implements TestOnly
{
    private BufferedOutput $buffer;

    /**
     * Messages written so far, one entry per line
     *
     * @var array
     */
    private $messages = [];

    public function __construct()
    {
        $this->buffer = new BufferedOutput();
        parent::__construct(PolyOutput::FORMAT_ANSI, OutputInterface::VERBOSITY_DEBUG, false, $this->buffer);
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        $this->drainBuffer();
        return $this->messages;
    }

    /**
     * Clear all messages
     */
    public function clear()
    {
        $this->buffer->fetch();
        $this->messages = [];
    }

    /**
     * Get messages with the given filter
     *
     * @param string $containing
     * @return array Filtered array
     */
    public function filterMessages($containing)
    {
        return array_values(array_filter(
            $this->getMessages() ?? [],
            fn($content) => stripos($content ?? '', $containing ?? '') !== false
        ));
    }

    /**
     * Count all messages containing the given substring
     *
     * @param string $containing Message to filter by
     * @return int
     */
    public function countMessages($containing = null)
    {
        if ($containing) {
            $messages = $this->filterMessages($containing);
        } else {
            $messages = $this->getMessages();
        }
        return count($messages ?? []);
    }

    /**
     * Move anything written to the buffer since the last read into the message list
     */
    private function drainBuffer(): void
    {
        $written = $this->buffer->fetch();
        if ($written === '') {
            return;
        }

        foreach (explode("\n", rtrim($written, "\n")) as $message) {
            $this->messages[] = $message;
        }
    }
}
