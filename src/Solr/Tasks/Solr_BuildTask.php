<?php
namespace SilverStripe\FullTextSearch\Solr\Tasks;

use SilverStripe\PolyExecution\PolyOutput;
use Override;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\FullTextSearch\Utils\Logging\SearchLogFactory;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Abstract class for build tasks
 */
class Solr_BuildTask extends BuildTask
{

    protected string $title = 'Solr BuildTask';

    protected static string $description = 'Build solr search indexes';

    protected bool $enabled = false;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * Get the monolog logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Assign a new logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SearchLogFactory
     */
    protected function getLoggerFactory()
    {
        return Injector::inst()->get(SearchLogFactory::class);
    }

    /**
     * Setup task
     *
     * @param HTTPRequest $request
     */
    #[Override]
    public function execute(InputInterface $request, PolyOutput $output): int
    {
        $name = static::class;
        $verbose = $request->getVar('verbose');

        // Set new logger
        $logger = $this
            ->getLoggerFactory()
            ->getOutputLogger($name, $verbose);
        $this->setLogger($logger);
    }
}
