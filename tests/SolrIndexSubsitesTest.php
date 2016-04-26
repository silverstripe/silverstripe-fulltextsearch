<?php

if (class_exists('Phockito')) {
    Phockito::include_hamcrest(false);
}

/**
 * Subsite specific solr testing
 */
class SolrIndexSubsitesTest extends SapphireTest {

    public static $fixture_file = 'SolrIndexSubsitesTest.yml';

    /**
     * @var SolrIndexSubsitesTest_Index
     */
    private static $index = null;

    protected $server = null;

    public function setUp()
    {
        // Prevent parent::setUp() crashing on db build
        if (!class_exists('Subsite')) {
            $this->skipTest = true;
        }

        parent::setUp();

        $this->server = $_SERVER;

        if (!class_exists('Phockito')) {
            $this->skipTest = true;
            $this->markTestSkipped("These tests need the Phockito module installed to run");
            return;
        }

        // Check versioned available
        if (!class_exists('Subsite')) {
            $this->skipTest = true;
            $this->markTestSkipped('The subsite module is not installed');
            return;
        }

        if (self::$index === null) {
            self::$index = singleton('SolrIndexSubsitesTest_Index');
        }

        SearchUpdater::bind_manipulation_capture();

        Config::inst()->update('Injector', 'SearchUpdateProcessor', array(
            'class' => 'SearchUpdateImmediateProcessor'
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function tearDown()
    {
        if($this->server) {
            $_SERVER = $this->server;
            $this->server = null;
        }
        parent::tearDown();
    }

    protected function getServiceMock()
    {
        return Phockito::mock('Solr4Service');
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
        $class = ClassInfo::baseDataClass($object);
        $variants = array();

        // Check subsite
        if(class_exists('Subsite') && $object->hasOne('Subsite')) {
            $variants[] = '"SearchVariantSubsites":"' . $subsiteID. '"';
        }

        // Check versioned
        if($stage) {
            $variants[] = '"SearchVariantVersioned":"' . $stage . '"';
        }
        return $id.'-'.$class.'-{'.implode(',',$variants).'}';
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
        Phockito::reset($serviceMock);
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
        Phockito::verify($serviceMock)->addDocument($doc1);
        Phockito::verify($serviceMock)->addDocument($doc2);

    }
}

class SolrIndexSubsitesTest_Index extends SolrIndex
{
    public function init()
    {
        $this->addClass('File');
        $this->addClass('SiteTree');
        $this->addAllFulltextFields();
    }
}
