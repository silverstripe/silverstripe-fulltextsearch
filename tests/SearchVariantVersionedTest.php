<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Index;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_Item;
use SilverStripe\FullTextSearch\Tests\SearchVariantVersionedTest\SearchVariantVersionedTest_IndexNoStage;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;

class SearchVariantVersionedTest extends SapphireTest
{
    /**
     * @var SearchVariantVersionedTest_Index
     */
    private static $index = null;

    protected static $extra_dataobjects = array(
        SearchVariantVersionedTest_Item::class
    );

    protected function setUp()
    {
        Config::modify()->set(SearchUpdater::class, 'flush_on_shutdown', false);

        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SearchVariantVersionedTest_Index::class);
        }

        SearchUpdater::bind_manipulation_capture();

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, array(
            'class' => SearchUpdateImmediateProcessor::class
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function testPublishing()
    {
        // Check that write updates Stage

        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Stage')
        ), self::$index->getAdded(array('ID', '_versionedstage')));

        // Check that publish updates Live

        self::$index->reset();

        $item->copyVersionToStage('Stage', 'Live');

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Stage'),
            array('ID' => $item->ID, '_versionedstage' => 'Live')
        ), self::$index->getAdded(array('ID', '_versionedstage')));

        // Just update a SiteTree field, and check it updates Stage

        self::$index->reset();

        $item->Title = "Pow!";
        $item->write();

        SearchUpdater::flush_dirty_indexes();

        $expected = array(array(
            'ID' => $item->ID,
            '_versionedstage' => 'Stage'
        ));
        $added = self::$index->getAdded(array('ID', '_versionedstage'));
        $this->assertEquals($expected, $added);

        // Test unpublish

        self::$index->reset();

        $item->deleteFromStage('Live');

        SearchUpdater::flush_dirty_indexes();

        $this->assertCount(1, self::$index->deleted);
        $this->assertEquals(
            SiteTree::class,
            self::$index->deleted[0]['base']
        );
        $this->assertEquals(
            $item->ID,
            self::$index->deleted[0]['id']
        );
        $this->assertEquals(
            'Live',
            self::$index->deleted[0]['state'][SearchVariantVersioned::class]
        );
    }

    public function testExcludeVariantState()
    {
        $index = singleton(SearchVariantVersionedTest_IndexNoStage::class);
        FullTextSearch::force_index_list($index);

        // Check that write doesn't update stage
        $item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
        $item->write();
        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals(array(), $index->getAdded(array('ID', '_versionedstage')));

        // Check that publish updates Live
        $index->reset();

        $item->copyVersionToStage('Stage', 'Live');

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals(array(
            array('ID' => $item->ID, '_versionedstage' => 'Live')
        ), $index->getAdded(array('ID', '_versionedstage')));
    }
}
