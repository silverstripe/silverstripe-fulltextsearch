<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
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
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

class SolrIndexVersionedTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected $oldMode = null;

    protected static $index = null;

    protected static $extra_dataobjects = [
        SearchVariantVersionedTest_Item::class,
        SolrIndexVersionedTest_Object::class,
    ];

    protected function setUp(): void
    {
        // Need to be set before parent::setUp() since they're executed before the tests start
        Config::modify()->set(SearchVariantSubsites::class, 'enabled', false);

        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SolrVersionedTest_Index::class);
        }

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, [
            'class' => SearchUpdateImmediateProcessor::class
        ]);

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();

        $this->oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
    }

    protected function tearDown(): void
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
        return $id . '-' . $class . '-{' . json_encode(SearchVariantVersioned::class) . ':"' . $stage . '"}';
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
        $doc = new Apache_Solr_Document();
        $doc->setField('_documentid', $this->getExpectedDocumentId($object, $stage));
        $doc->setField('ClassName', $class);
        $doc->setField(DataObject::getSchema()->baseDataClass($class) . '_TestText', $value);
        $doc->setField('_versionedstage', $stage);
        $doc->setField('ID', (int) $object->ID);
        $doc->setField('ClassHierarchy', SearchIntrospection::hierarchy($class));
        $doc->setFieldBoost('ID', false);
        $doc->setFieldBoost('ClassHierarchy', false);

        return $doc;
    }

    public function testPublishing()
    {
        $classesToSkip = [SearchVariantVersionedTest_Item::class, SolrIndexVersionedTest_Object::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // Check that write updates Stage
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::DRAFT);
        $doc2 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::DRAFT);

        // Ensure correct call is made to Solr
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(2))
            ->method('addDocument')
            ->withConsecutive(
                [$this->equalTo($doc1)],
                [$this->equalTo($doc2)]
            );

        SearchUpdater::flush_dirty_indexes();

        // Check that write updates Live
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $object = new SolrIndexVersionedTest_Object(array('TestText' => 'Bar'));
        $object->write();
        $object->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $doc1 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::DRAFT);
        $doc2 = $this->getSolrDocument(SearchVariantVersionedTest_Item::class, $item, 'Foo', Versioned::LIVE);
        $doc3 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::DRAFT);
        $doc4 = $this->getSolrDocument(SolrIndexVersionedTest_Object::class, $object, 'Bar', Versioned::LIVE);

        // Ensure correct call is made to Solr
        $this->getServiceMock(['addDocument', 'commit'])
            ->expects($this->exactly(4))
            ->method('addDocument')
            ->withConsecutive(
                [$doc1],
                [$doc2],
                [$doc3],
                [$doc4]
            );

        SearchUpdater::flush_dirty_indexes();
    }

    public function testDelete()
    {
        $classesToSkip = [SearchVariantVersionedTest_Item::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        // Delete the live record (not the stage)
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        Versioned::set_stage(Versioned::LIVE);
        $id = clone $item;
        $item->delete();

        // Check that only the 'Live' version is deleted
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->getExpectedDocumentId($id, Versioned::LIVE));

        SearchUpdater::flush_dirty_indexes();

        // Delete the stage record
        Versioned::set_stage(Versioned::DRAFT);

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Too'));
        $item->write();
        $item->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $id = clone $item;
        $item->delete();

        // Check that only the 'Stage' version is deleted
        $this->getServiceMock(['addDocument', 'commit', 'deleteById'])
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->with($this->getExpectedDocumentId($id, Versioned::DRAFT));

        SearchUpdater::flush_dirty_indexes();
    }
}
