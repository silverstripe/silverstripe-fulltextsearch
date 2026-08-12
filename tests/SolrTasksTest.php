<?php

namespace SilverStripe\FullTextSearch\Tests;

use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Solr\Reindex\Handlers\SolrReindexImmediateHandler;
use SilverStripe\FullTextSearch\Solr\Tasks\Solr_BuildTask;
use SilverStripe\FullTextSearch\Solr\Tasks\Solr_Configure;
use SilverStripe\FullTextSearch\Solr\Tasks\Solr_Reindex;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * Covers the CLI surface of the solr build tasks, which the reindex tests don't exercise
 * because they build an ArrayInput (which does not validate against the option definition)
 */
class SolrTasksTest extends SapphireTest
{
    protected $usesDatabase = false;

    /**
     * The reindex task is invoked with option values, both by a person on the CLI and by
     * SolrReindexImmediateHandler, so each option has to accept one
     */
    public function testReindexOptionsAcceptValues()
    {
        $input = new ArgvInput(
            [
                'sake',
                '--index=SolrReindexTest_Index',
                '--class=Page',
                '--groups=6',
                '--group=2',
                '--variantstate={"Variant":"1"}',
            ],
            new InputDefinition(Solr_Reindex::create()->getOptions())
        );

        $this->assertSame('SolrReindexTest_Index', $input->getOption('index'));
        $this->assertSame('Page', $input->getOption('class'));
        $this->assertSame('6', $input->getOption('groups'));
        $this->assertSame('2', $input->getOption('group'));
        $this->assertSame('{"Variant":"1"}', $input->getOption('variantstate'));
    }

    /**
     * is_enabled is only honoured by BuildTask::isEnabled() if the Config layer can read it
     */
    public function testTasksAreEnabledViaConfig()
    {
        $this->assertFalse(Config::inst()->get(Solr_BuildTask::class, 'is_enabled'));
        $this->assertTrue(Config::inst()->get(Solr_Configure::class, 'is_enabled'));
        $this->assertTrue(Config::inst()->get(Solr_Reindex::class, 'is_enabled'));

        $this->assertTrue(Solr_Configure::create()->isEnabled());
        $this->assertTrue(Solr_Reindex::create()->isEnabled());

        Config::modify()->set(Solr_Reindex::class, 'is_enabled', false);
        $this->assertFalse(Solr_Reindex::create()->isEnabled());
    }

    /**
     * Reindex jobs queued before the CMS 6 upgrade hold the unqualified class name in their
     * stored job data, so the handler has to accept that as well as the class name
     */
    public function testTaskNamesAreResolvedToClassNames()
    {
        $handler = new SolrReindexImmediateHandler();

        $this->assertSame(Solr_Reindex::class, $handler->resolveTaskClass(Solr_Reindex::class));
        $this->assertSame(Solr_Reindex::class, $handler->resolveTaskClass('Solr_Reindex'));
        $this->assertSame(Solr_Reindex::class, $handler->resolveTaskClass('solr-reindex'));
        $this->assertSame(Solr_Reindex::class, $handler->resolveTaskClass('tasks:solr-reindex'));
    }

    /**
     * The command is logged so that someone can re-run a single group by hand - index names,
     * class names and the JSON variant state all contain characters a shell would mangle
     */
    public function testLoggedCommandIsShellSafe()
    {
        $handler = new class extends SolrReindexImmediateHandler {
            public function getCommandLine(string $taskClass, array $params): string
            {
                return $this->buildCommandLine($taskClass, $params);
            }
        };

        $variantState = json_encode(['SilverStripe\\Versioned\\Variant' => 'Stage']);
        $command = $handler->getCommandLine(Solr_Reindex::class, [
            '--index' => 'My\\Index\\Class',
            '--variantstate' => $variantState,
            '--verbose' => true,
        ]);

        $this->assertStringStartsWith('sake tasks:solr-reindex ', $command);
        $this->assertStringContainsString("--index='My\\Index\\Class'", $command);
        $this->assertStringContainsString("--variantstate='{$variantState}'", $command);

        // Flags don't take a value, so they're passed through as-is
        $this->assertStringEndsWith(' --verbose', $command);
    }

    public function testUnknownTaskNameThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        (new SolrReindexImmediateHandler())->resolveTaskClass('not-a-task');
    }
}
