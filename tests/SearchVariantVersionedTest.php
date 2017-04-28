<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex_Recording;
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

    public function setUp()
    {
        parent::setUp();

        if (self::$index === null) {
            self::$index = singleton(SearchVariantVersionedTest_Index::class);
        }

        SearchUpdater::bind_manipulation_capture();

        Config::modify()->set('Injector', SearchUpdateProcessor::class, array(
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
