<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
use SilverStripe\FullTextSearch\Solr\Services\Solr3Service;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrIndexVersionedTest_Object;
use SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest\SolrVersionedTest_Index;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Versioned\Versioned;

class SolrIndexVersionedTest extends SapphireTest
{
    protected $oldMode = null;

    protected static $index = null;

    protected static $extra_dataobjects = array(
        SearchVariantVersionedTest_Item::class,
        SolrIndexVersionedTest_Object::class
    );

    protected function setUp()
    {
        Config::modify()->set(SearchUpdater::class, 'flush_on_shutdown', false);

        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SolrVersionedTest_Index::class);
        }

        SearchUpdater::bind_manipulation_capture();

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, array(
            'class' => SearchUpdateImmediateProcessor::class
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();

        $this->oldMode = Versioned::get_reading_mode();
        Versioned::set_stage('Stage');
    }

    public function tearDown()
    {
        Versioned::set_reading_mode($this->oldMode);
        parent::tearDown();
    }

    protected function getServiceMock($setMethods = array())
    {
        // Setup mock
        /** @var SilverStripe\FullTextSearch\Solr\Services\Solr3Service|ObjectProphecy $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->setMethods($setMethods)
            ->getMock();

        self::$index->setService($serviceMock);

        return $serviceMock;
    }

    /**
     * @param DataObject $object Item being added
     * @param string $stage
     * @return string
     */
    protected function getExpectedDocumentId($object, $stage)
    {
        $id = $object->ID;
        $class = DataObject::getSchema()->baseDataClass($object);
        // Prevent subsites from breaking tests
        // TODO: Subsites currently isn't migrated. This needs to be fixed when subsites is fixed.
        $subsites = '';
        if (class_exists('Subsite') && DataObject::getSchema()->hasOneComponent($object->getClassName(), 'Subsite')) {
            $subsites = '"SearchVariantSubsites":"0",';
        }
        return $id.'-'.$class.'-{'.$subsites. json_encode(SearchVariantVersioned::class) . ':"'.$stage.'"}';
    }

    /**
     * @param string $class
     * @param DataObject $object Item being added
     * @param string $value Value for class
     * @param string $stage Stage updated
     * @return Apache_Solr_Document
     */
    protected function getSolrDocument($class, $object, $value, $stage)
    {
        $doc = new \Apache_Solr_Document();
        $doc->setField('_documentid', $this->getExpectedDocumentId($object, $stage));
        $doc->setField('ClassName', $class);
        $doc->setField(DataObject::getSchema()->baseDataClass($class) . '_TestText', $value);
        $doc->setField('_versionedstage', $stage);
        $doc->setField('ID', $object->ID);
        $doc->setField('ClassHierarchy', SearchIntrospection::hierarchy($class));
        $doc->setFieldBoost('ID', false);
        $doc->setFieldBoost('ClassHierarchy', false);

        return $doc;
    }

    public function testPublishing()
    {
        // Check that write updates Stage
        Versioned::set_stage('Stage');

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', 'Stage');
        $doc2 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', 'Stage');

        // Ensure correct call is made to Solr
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(2))
            ->method('addDocument')
            ->withConsecutive(
                [
                    $this->equalTo($doc1),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ],
                [
                    $this->equalTo($doc2),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ]
            );

        SearchUpdater::flush_dirty_indexes();


        // Check that write updates Live
        Versioned::set_stage('Stage');

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $item->copyVersionToStage('Stage', 'Live');

        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();
        $object->copyVersionToStage('Stage', 'Live');

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', 'Stage');
        $doc2 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', 'Live');
        $doc3 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', 'Stage');
        $doc4 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', 'Live');

        // Ensure correct call is made to Solr
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(4))
            ->method('addDocument')
            ->withConsecutive(
                [
                    $this->equalTo($doc1),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ],
                [
                    $this->equalTo($doc2),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ],
                [
                    $this->equalTo($doc3),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ],
                [
                    $this->equalTo($doc4),
                    $this->anything(),
                    $this->anything(),
                    $this->anything(),
                    $this->anything()
                ]
            );

        SearchUpdater::flush_dirty_indexes();
    }

    public function testDelete()
    {
        // Delete the live record (not the stage)
        Versioned::set_stage('Stage');

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage('Stage', 'Live');
        Versioned::set_stage('Live');
        $id = clone $item;
        $item->delete();

        // Check that only the 'Live' version is deleted
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->equalTo($this->getExpectedDocumentId($id, 'Live')));

        SearchUpdater::flush_dirty_indexes();

        // Delete the stage record
        Versioned::set_stage('Stage');

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage('Stage', 'Live');
        $id = clone $item;
        $item->delete();

        // Check that only the 'Stage' version is deleted
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->equalTo($this->getExpectedDocumentId($id, 'Stage')));

        SearchUpdater::flush_dirty_indexes();
    }
}
