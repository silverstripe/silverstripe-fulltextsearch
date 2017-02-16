<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Tests\SolrVersionedTest\SolrDocumentMatcher;
use SilverStripe\FullTextSearch\Tests\SolrVersionedTest\SolrIndexVersionedTest_Object;
use SilverStripe\FullTextSearch\Tests\SolrVersionedTest\SolrVersionedTest_Index;
use SilverStripe\Versioned\Versioned;

if (class_exists('Phockito')) {
    Phockito::include_hamcrest(false);
}

class SolrIndexVersionedTest extends SapphireTest
{
    protected $oldMode = null;

    protected static $index = null;

    protected $extraDataObjects = array(
        'SearchVariantVersionedTest_Item',
        'SolrIndexVersionedTest_Object',
    );

    public function setUp()
    {
        parent::setUp();

        if (!class_exists('Phockito')) {
            $this->skipTest = true;
            $this->markTestSkipped("These tests need the Phockito module installed to run");
            return;
        }

        // Check versioned available
        if (!class_exists('Versioned')) {
            $this->skipTest = true;
            $this->markTestSkipped('The versioned decorator is not installed');
            return;
        }

        if (self::$index === null) {
            self::$index = singleton('SolrVersionedTest_Index');
        }

        SearchUpdater::bind_manipulation_capture();

        Config::inst()->update('Injector', 'SearchUpdateProcessor', array(
            'class' => 'SearchUpdateImmediateProcessor'
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();

        $this->oldMode = Versioned::get_reading_mode();
        Versioned::reading_stage('Stage');
    }

    public function tearDown()
    {
        Versioned::set_reading_mode($this->oldMode);
        parent::tearDown();
    }

    protected function getServiceMock()
    {
        return Phockito::mock('Solr3Service');
    }

    /**
     * @param DataObject $object Item being added
     * @param string $stage
     * @return string
     */
    protected function getExpectedDocumentId($object, $stage)
    {
        $id = $object->ID;
        $class = ClassInfo::baseDataClass($object);
        // Prevent subsites from breaking tests
        $subsites = '';
        if(class_exists('Subsite') && $object->hasOne('Subsite')) {
            $subsites = '"SearchVariantSubsites":"0",';
        }
        return $id.'-'.$class.'-{'.$subsites.'"SearchVariantVersioned":"'.$stage.'"}';
    }

    public function testPublishing()
    {

        // Setup mocks
        $serviceMock = $this->getServiceMock();
        self::$index->setService($serviceMock);

        // Check that write updates Stage
        Versioned::reading_stage('Stage');
        Phockito::reset($serviceMock);
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();
        SearchUpdater::flush_dirty_indexes();
        $doc1 = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($item, 'Stage'),
            'ClassName' => 'SearchVariantVersionedTest_Item',
            'SearchVariantVersionedTest_Item_TestText' => 'Foo',
            '_versionedstage' => 'Stage'
        ));
        $doc2 = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($object, 'Stage'),
            'ClassName' => 'SolrIndexVersionedTest_Object',
            'SolrIndexVersionedTest_Object_TestText' => 'Bar',
            '_versionedstage' => 'Stage'
        ));
        Phockito::verify($serviceMock)->addDocument($doc1);
        Phockito::verify($serviceMock)->addDocument($doc2);

        // Check that write updates Live
        Versioned::reading_stage('Stage');
        Phockito::reset($serviceMock);
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $item->publish('Stage', 'Live');
        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();
        $object->publish('Stage', 'Live');
        SearchUpdater::flush_dirty_indexes();
        $doc = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($item, 'Live'),
            'ClassName' => 'SearchVariantVersionedTest_Item',
            'SearchVariantVersionedTest_Item_TestText' => 'Foo',
            '_versionedstage' => 'Live'
        ));
        $doc2 = new SolrDocumentMatcher(array(
            '_documentid' => $this->getExpectedDocumentId($object, 'Live'),
            'ClassName' => 'SolrIndexVersionedTest_Object',
            'SolrIndexVersionedTest_Object_TestText' => 'Bar',
            '_versionedstage' => 'Live'
        ));
        Phockito::verify($serviceMock)->addDocument($doc);
        Phockito::verify($serviceMock)->addDocument($doc2);
    }

    public function testDelete()
    {
        // Setup mocks
        $serviceMock = $this->getServiceMock();
        self::$index->setService($serviceMock);

        // Delete the live record (not the stage)
        Versioned::reading_stage('Stage');
        Phockito::reset($serviceMock);
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->publish('Stage', 'Live');
        Versioned::reading_stage('Live');
        $id = clone $item;
        $item->delete();
        SearchUpdater::flush_dirty_indexes();
        Phockito::verify($serviceMock, 1)
            ->deleteById($this->getExpectedDocumentId($id, 'Live'));
        Phockito::verify($serviceMock, 0)
            ->deleteById($this->getExpectedDocumentId($id, 'Stage'));

        // Delete the stage record
        Versioned::reading_stage('Stage');
        Phockito::reset($serviceMock);
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->publish('Stage', 'Live');
        $id = clone $item;
        $item->delete();
        SearchUpdater::flush_dirty_indexes();
        Phockito::verify($serviceMock, 1)
            ->deleteById($this->getExpectedDocumentId($id, 'Stage'));
        Phockito::verify($serviceMock, 0)
            ->deleteById($this->getExpectedDocumentId($id, 'Live'));
    }
}
