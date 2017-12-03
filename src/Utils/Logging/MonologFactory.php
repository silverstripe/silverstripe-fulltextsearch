<?php

namespace SilverStripe\FullTextSearch\Utils\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Utils\Logging\QueuedJobLogHandler;

/**
 * Provides logging based on monolog
 */
class MonologFactory implements SearchLogFactory
{
    public function getOutputLogger($name, $verbose)
    {
        $logger = $this->getLoggerFor($name);
        $formatter = $this->getFormatter();

        // Notice handling
        if ($verbose) {
            $messageHandler = $this->getStreamHandler($formatter, 'php://stdout', Logger::INFO);
            $logger->pushHandler($messageHandler);
        }

        // Error handling. buble is false so that errors aren't logged twice
        $errorHandler = $this->getStreamHandler($formatter, 'php://stderr', Logger::ERROR, false);
        $logger->pushHandler($errorHandler);
        return $logger;
    }

    public function getQueuedJobLogger($job)
    {
        $logger = $this->getLoggerFor(get_class($job));
        $handler = $this->getJobHandler($job);
        $logger->pushHandler($handler);
        return $logger;
    }

    /**
     * Generate a handler for the given stream
     *
     * @param FormatterInterface $formatter
     * @param string $stream Name of preferred stream
     * @param int $level
     * @param bool $bubble
     * @return HandlerInterface
     */
    protected function getStreamHandler(FormatterInterface $formatter, $stream, $level = Logger::DEBUG, $bubble = true)
    {
        // Unless cli, force output to php://output
        $stream = Director::is_cli() ? $stream : 'php://output';
        $handler = Injector::inst()->createWithArgs(
            StreamHandler::class,
            array($stream, $level, $bubble)
        );
        $handler->setFormatter($formatter);
        return $handler;
    }

    /**
     * Gets a formatter for standard output
     *
     * @return FormatterInterface
     */
    protected function getFormatter()
    {
        // Get formatter
        $format = LineFormatter::SIMPLE_FORMAT;
        if (!Director::is_cli()) {
            $format = "<p>$format</p>";
        }
        return Injector::inst()->createWithArgs(
            LineFormatter::class,
            array($format)
        );
    }

    /**
     * Get a logger for a named class
     *
     * @param string $name
     * @return Logger
     */
    protected function getLoggerFor($name)
    {
        return Injector::inst()->createWithArgs(
            Logger::class,
            array(strtolower($name))
        );
    }

    /**
     * Generate handler for a job object
     *
     * @param QueuedJob $job
     * @return HandlerInterface
     */
    protected function getJobHandler($job)
    {
        return Injector::inst()->createWithArgs(
            QueuedJobLogHandler::class,
            array($job, Logger::INFO)
        );
    }
}
