<?php
namespace SilverStripe\FullTextSearch\Solr\Tasks;

use SilverStripe\PolyExecution\PolyOutput;
use Override;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Abstract class for build tasks
 */
abstract class Solr_BuildTask extends BuildTask
{

    protected string $title = 'Solr BuildTask';

    protected static string $description = 'Build solr search indexes';

    protected bool $is_enabled = false;

    protected bool $verbose = false;

    protected ?PolyOutput $output = null;

    /**
     * Setup task
     *
     * @param HTTPRequest $request
     */
    protected function info(string $message, bool $hidden = true): void
    {
        if ($this->verbose) {
            $this->output->writeln($message);
        } elseif (!$hidden) {
            $this->output->writeln($message);
        }
    }

    abstract public function execute(InputInterface $request, PolyOutput $output): int;
}
