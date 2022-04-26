<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use Page;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Solr\Services\Solr4Service;
use SilverStripe\FullTextSearch\Tests\SolrIndexSubsitesTest\SolrIndexSubsitesTest_Index;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrDocumentMatcher;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

/**
 * Subsite specific solr testing
 */
class SolrIndexSubsitesTest extends SapphireTest
{
    protected static $fixture_file = 'SolrIndexSubsitesTest/SolrIndexSubsitesTest.yml';

    /**
     * @var SolrIndexSubsitesTest_Index
     */
    private static $index = null;

    protected $server = null;

    protected function setUp(): void
    {
        // Prevent parent::setUp() crashing on db build
        if (!class_exists(Subsite::class)) {
            static::$fixture_file = null;
        }

        parent::setUp();

        if (!class_exists(Subsite::class)) {
            $this->markTestSkipped("These tests need the Subsite module installed to run");
        }

        $this->server = $_SERVER;

        if (self::$index === null) {
            self::$index = singleton(SolrIndexSubsitesTest_Index::class);
        }

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, [
            'class' => SearchUpdateImmediateProcessor::class,
        ]);

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    protected function tearDown(): void
    {
        if ($this->server) {
            $_SERVER = $this->server;
            $this->server = null;
        }
        parent::tearDown();
    }

    protected function getServiceMock()
    {
        return $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['addDocument', 'commit'])
            ->getMock();
    }

    /**
     * @param DataObject $object Item being added
     * @param int $subsiteID
     * @param string $stage
     * @return string
     */
    protected function getExpectedDocumentId($object, $subsiteID, $stage = null)
    {
        $id = $object->ID;
        $class = DataObject::getSchema()->baseDataClass($object);
        $variants = array();

        // Check subsite
        if (class_exists(Subsite::class)
            && DataObject::getSchema()->hasOneComponent($object->getClassName(), 'Subsite')
        ) {
            $variants[] = '"SearchVariantSubsites":"' . $subsiteID . '"';
        }

        // Check versioned
        if ($stage) {
            $variants[] = '"SearchVariantVersioned":"' . $stage . '"';
        }
        return $id . '-' . $class . '-{' . implode(',', $variants) . '}';
    }

    public function testPublishing()
    {
        $classesToSkip = [SiteTree::class, File::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // Setup mocks
        $serviceMock = $this->getServiceMock();
        self::$index->setService($serviceMock);

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

        // Add records to first subsite
        Versioned::set_stage(Versioned::DRAFT);
        $_SERVER['HTTP_HOST'] = 'www.subsite1.com';

        $file = new File();
        $file->Title = 'My File';
        $file->SubsiteID = $subsite1->ID;
        $file->write();

        $page = new Page();
        $page->Title = 'My Page';
        $page->SubsiteID = $subsite1->ID;
        $page->write();

        $doc1 = new Apache_Solr_Document([
            '_documentid' => $this->getExpectedDocumentId($page, $subsite1->ID, 'Stage'),
            'ClassName' => 'Page',
            'SiteTree_Title' => 'My Page',
            '_versionedstage' => 'Stage',
            '_subsite' => $subsite1->ID,
        ]);

        $doc2 = new Apache_Solr_Document([
            '_documentid' => $this->getExpectedDocumentId($file, $subsite1->ID),
            'ClassName' => File::class,
            'File_Title' => 'My File',
            '_subsite' => $subsite1->ID,
        ]);

        $serviceMock
            ->expects($this->exactly(2))
            ->method('addDocument')
            ->withConsecutive($doc1, $doc2);

        SearchUpdater::flush_dirty_indexes();
    }

    public function testCorrectSubsiteIDOnPageWrite()
    {
        $mockWrites = [
            '3367:SiteTree:a:1:{s:22:"SearchVariantVersioned";s:4:"Live";}' => [
                'base' => 'SilverStripe\\CMS\\Model\\SiteTree',
                'class' => 'Page',
                'id' => 3367,
                'statefulids' => [
                    [
                        'id' => 3367,
                        'state' => [
                            'SearchVariantVersioned' => 'Live',
                        ],
                    ],
                ],
                'fields' => [
                    'SilverStripe\\CMS\\Model\\SiteTree:ClassName' => 'Page',
                    'SilverStripe\\CMS\\Model\\SiteTree:LastEdited' => '2016-12-08 23:55:30',
                    'SilverStripe\\CMS\\Model\\SiteTree:Created' => '2016-11-30 05:23:58',
                    'SilverStripe\\CMS\\Model\\SiteTree:URLSegment' => 'test',
                    'SilverStripe\\CMS\\Model\\SiteTree:Title' => 'Test Title',
                    'SilverStripe\\CMS\\Model\\SiteTree:Content' => '<p>test content</p>',
                    'SilverStripe\\CMS\\Model\\SiteTree:MetaDescription' => 'a solr test',
                    'SilverStripe\\CMS\\Model\\SiteTree:ShowInMenus' => 1,
                    'SilverStripe\\CMS\\Model\\SiteTree:ShowInSearch' => 1,
                    'SilverStripe\\CMS\\Model\\SiteTree:Sort' => 77,
                    'SilverStripe\\CMS\\Model\\SiteTree:HasBrokenFile' => 0,
                    'SilverStripe\\CMS\\Model\\SiteTree:HasBrokenLink' => 0,
                    'SilverStripe\\CMS\\Model\\SiteTree:CanViewType' => 'Inherit',
                    'SilverStripe\\CMS\\Model\\SiteTree:CanEditType' => 'Inherit',
                    'SilverStripe\\CMS\\Model\\SiteTree:Locale' => 'en_NZ',
                    'SilverStripe\\CMS\\Model\\SiteTree:SubsiteID' => 0,
                    'Page:ID' => 3367,
                    'Page:MetaKeywords' => null,
                ],
            ],
        ];
        $variant = new SearchVariantSubsites();
        $tmpMockWrites = $mockWrites;
        $variant->extractManipulationWriteState($tmpMockWrites);

        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);

            $this->assertArrayHasKey(SearchVariantSubsites::class, $statefulIDs['state']);
            $this->assertEquals(0, $statefulIDs['state'][SearchVariantSubsites::class]);
        }

        $subsite = $this->objFromFixture(Subsite::class, 'subsite1');
        $tmpMockWrites = $mockWrites;
        $tmpMockWrites['3367:SiteTree:a:1:{s:22:"SearchVariantVersioned";s:4:"Live";}']['fields'][SiteTree::class . ':SubsiteID'] = $subsite->ID;

        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);

            $this->assertArrayHasKey(SearchVariantSubsites::class, $statefulIDs['state']);
            $this->assertEquals($subsite->ID, $statefulIDs['state'][SearchVariantSubsites::class]);
        }
    }

    public function testCorrectSubsiteIDOnFileWrite()
    {
        $subsiteIDs = ['0'] + $this->allFixtureIDs(Subsite::class);
        $subsiteIDs = array_map(function ($v) {
            return (string) $v;
        }, $subsiteIDs ?? []);
        $mockWrites = [
            '35910:File:a:0:{}' => [
                'base' => File::class,
                'class' => File::class,
                'id' => 35910,
                'statefulids' => [
                    [
                        'id' => 35910,
                        'state' => [],
                    ],
                ],
                'fields' => [
                    File::class . ':ClassName' => Image::class,
                    File::class . ':ShowInSearch' => 1,
                    File::class . ':ParentID' => 26470,
                    File::class . ':Filename' => 'assets/Uploads/pic.jpg',
                    File::class . ':Name' => 'pic.jpg',
                    File::class . ':Title' => 'pic',
                    File::class . ':SubsiteID' => 0,
                    File::class . ':OwnerID' => 661,
                    File::class . ':CurrentVersionID' => 22038,
                    File::class . ':LastEdited' => '2016-12-09 00:35:13',
                ],
            ],
        ];
        $variant = new SearchVariantSubsites();
        $tmpMockWrites = $mockWrites;
        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(count($subsiteIDs ?? []), $mockWrite['statefulids']);
            foreach ($mockWrite['statefulids'] as $statefulIDs) {
                $this->assertContains(
                    (string) $statefulIDs['state'][SearchVariantSubsites::class],
                    $subsiteIDs,
                    sprintf(
                        'Failed to assert that %s is in list of valid subsites: %s',
                        $statefulIDs['state'][SearchVariantSubsites::class],
                        implode(', ', $subsiteIDs)
                    )
                );
            }
        }

        $subsite = $this->objFromFixture(Subsite::class, 'subsite1');
        $tmpMockWrites = $mockWrites;
        $tmpMockWrites['35910:File:a:0:{}']['fields'][File::class . ':SubsiteID'] = $subsite->ID;

        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);
            $this->assertEquals($subsite->ID, $statefulIDs['state'][SearchVariantSubsites::class]);
        }
    }
}
