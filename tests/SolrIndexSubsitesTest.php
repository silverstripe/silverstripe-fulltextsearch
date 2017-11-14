<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Tests\SolrIndexSubsitesTest\SolrIndexSubsitesTest_Index;

if (class_exists('\Phockito')) {
    \Phockito::include_hamcrest(false);
}

/**
 * Subsite specific solr testing
 */
class SolrIndexSubsitesTest extends SapphireTest
{
    // @todo
    // protected static $fixture_file = 'SolrIndexSubsitesTest/SolrIndexSubsitesTest.yml';

    /**
     * @var SolrIndexSubsitesTest_Index
     */
    private static $index = null;

    protected $server = null;

    protected function setUp()
    {
        parent::setUp();

        // Prevent parent::setUp() crashing on db build
        if (!class_exists('Subsite')) {
            $this->skipTest = true;
            $this->markTestSkipped("These tests need the Subsite module installed to run");
        }

        $this->server = $_SERVER;

        if (!class_exists('\Phockito')) {
            $this->skipTest = true;
            $this->markTestSkipped("These tests need the \Phockito module installed to run");
            return;
        }

        if (self::$index === null) {
            self::$index = singleton('SolrIndexSubsitesTest_Index');
        }

        SearchUpdater::bind_manipulation_capture();

        Config::modify()->set('Injector', 'SearchUpdateProcessor', array(
            'class' => 'SearchUpdateImmediateProcessor'
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    protected function tearDown()
    {
        if ($this->server) {
            $_SERVER = $this->server;
            $this->server = null;
        }
        parent::tearDown();
    }

    protected function getServiceMock()
    {
        return \Phockito::mock('Solr4Service');
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
        if (class_exists('Subsite') && DataObject::getSchema()->hasOneComponent($object->getClassName(), 'Subsite')) {
            $variants[] = '"SearchVariantSubsites":"' . $subsiteID. '"';
        }

        // Check versioned
        if ($stage) {
            $variants[] = '"SearchVariantVersioned":"' . $stage . '"';
        }
        return $id.'-'.$class.'-{'.implode(',', $variants).'}';
    }

    public function testPublishing()
    {
        // Setup mocks
        $serviceMock = $this->getServiceMock();
        self::$index->setService($serviceMock);

        $subsite1 = $this->objFromFixture('Subsite', 'subsite1');

        // Add records to first subsite
        Versioned::reading_stage('Stage');
        $_SERVER['HTTP_HOST'] = 'www.subsite1.com';
        \Phockito::reset($serviceMock);
        $file = new File();
        $file->Title = 'My File';
        $file->SubsiteID = $subsite1->ID;
        $file->write();
        $page = new Page();
        $page->Title = 'My Page';
        $page->SubsiteID = $subsite1->ID;
        $page->write();
        SearchUpdater::flush_dirty_indexes();
        $doc1 = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($page, $subsite1->ID, 'Stage'),
            'ClassName' => 'Page',
            'SiteTree_Title' => 'My Page',
            '_versionedstage' => 'Stage',
            '_subsite' => $subsite1->ID
        ));
        $doc2 = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($file, $subsite1->ID),
            'ClassName' => 'File',
            'File_Title' => 'My File',
            '_subsite' => $subsite1->ID
        ));
        \Phockito::verify($serviceMock)->addDocument($doc1);
        \Phockito::verify($serviceMock)->addDocument($doc2);
    }

    public function testCorrectSubsiteIDOnPageWrite()
    {
        $mockWrites = array(
            '3367:SiteTree:a:1:{s:22:"SearchVariantVersioned";s:4:"Live";}' => array(
                'base' => 'SiteTree',
                'class' => 'Page',
                'id' => 3367,
                'statefulids' => array(
                    array(
                        'id' => 3367,
                        'state' => array(
                            'SearchVariantVersioned' => 'Live',
                        ),
                    ),
                ),
                'fields' => array(
                    'SiteTree:ClassName' => 'Page',
                    'SiteTree:LastEdited' => '2016-12-08 23:55:30',
                    'SiteTree:Created' => '2016-11-30 05:23:58',
                    'SiteTree:URLSegment' => 'test',
                    'SiteTree:Title' => 'Test Title',
                    'SiteTree:Content' => '<p>test content</p>',
                    'SiteTree:MetaDescription' => 'a solr test',
                    'SiteTree:ShowInMenus' => 1,
                    'SiteTree:ShowInSearch' => 1,
                    'SiteTree:Sort' => 77,
                    'SiteTree:HasBrokenFile' => 0,
                    'SiteTree:HasBrokenLink' => 0,
                    'SiteTree:CanViewType' => 'Inherit',
                    'SiteTree:CanEditType' => 'Inherit',
                    'SiteTree:Locale' => 'en_NZ',
                    'SiteTree:SubsiteID' => 0,
                    'Page:ID' => 3367,
                    'Page:MetaKeywords' => null,
                ),
            ),
        );
        $variant = new SearchVariantSubsites();
        $tmpMockWrites = $mockWrites;
        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);
            $this->assertEquals(0, $statefulIDs['state']['SearchVariantSubsites']);
        }

        $subsite = $this->objFromFixture('Subsite', 'subsite1');
        $tmpMockWrites = $mockWrites;
        $tmpMockWrites['3367:SiteTree:a:1:{s:22:"SearchVariantVersioned";s:4:"Live";}']['fields']['SiteTree:SubsiteID'] = $subsite->ID;

        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);
            $this->assertEquals($subsite->ID, $statefulIDs['state']['SearchVariantSubsites']);
        }
    }

    public function testCorrectSubsiteIDOnFileWrite()
    {
        $subsiteIDs = array('0') + $this->allFixtureIDs('Subsite');
        $mockWrites = array(
            '35910:File:a:0:{}' => array(
                'base' => 'File',
                'class' => 'File',
                'id' => 35910,
                'statefulids' => array(
                    array(
                        'id' => 35910,
                        'state' => array(),
                    ),
                ),
                'fields' => array(
                    'File:ClassName' => 'Image',
                    'File:ShowInSearch' => 1,
                    'File:ParentID' => 26470,
                    'File:Filename' => 'assets/Uploads/pic.jpg',
                    'File:Name' => 'pic.jpg',
                    'File:Title' => 'pic',
                    'File:SubsiteID' => 0,
                    'File:OwnerID' => 661,
                    'File:CurrentVersionID' => 22038,
                    'File:LastEdited' => '2016-12-09 00:35:13',
                ),
            ),
        );
        $variant = new SearchVariantSubsites();
        $tmpMockWrites = $mockWrites;
        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(count($subsiteIDs), $mockWrite['statefulids']);
            foreach ($mockWrite['statefulids'] as $statefulIDs) {
                $this->assertTrue(
                    in_array($statefulIDs['state']['SearchVariantSubsites'], $subsiteIDs),
                    sprintf('Failed to assert that %s is in list of valid subsites: %s', $statefulIDs['state']['SearchVariantSubsites'], implode(', ', $subsiteIDs))
                );
            }
        }

        $subsite = $this->objFromFixture('Subsite', 'subsite1');
        $tmpMockWrites = $mockWrites;
        $tmpMockWrites['35910:File:a:0:{}']['fields']['File:SubsiteID'] = $subsite->ID;

        $variant->extractManipulationWriteState($tmpMockWrites);
        foreach ($tmpMockWrites as $mockWrite) {
            $this->assertCount(1, $mockWrite['statefulids']);
            $statefulIDs = array_shift($mockWrite['statefulids']);
            $this->assertEquals($subsite->ID, $statefulIDs['state']['SearchVariantSubsites']);
        }
    }
}
