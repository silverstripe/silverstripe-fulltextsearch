<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateProcessor;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasOne;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Index;

class SearchUpdaterTest extends SapphireTest
{
    protected $usesDatabase = true;

    private static $index = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$index === null) {
            self::$index = SearchUpdaterTest_Index::singleton();
        } else {
            self::$index->reset();
        }

        Config::modify()->set(Injector::class, SearchUpdateProcessor::class, array(
            'class' => SearchUpdateImmediateProcessor::class
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function testBasic()
    {
        $item = new SearchUpdaterTest_Container();
        $item->write();
        $this->assertTrue(true);

        // TODO: Make sure changing field1 updates item.
        // TODO: Get updating just field2 to not update item (maybe not possible - variants complicate)
    }

    public function testHasOneHook()
    {
        $classesToSkip = [SearchUpdaterTest_Container::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);

        $hasOne = new SearchUpdaterTest_HasOne();
        $hasOne->write();

        $alternateHasOne = new SearchUpdaterTest_HasOne();
        $alternateHasOne->write();

        $container1 = new SearchUpdaterTest_Container();
        $container1->HasOneObjectID = $hasOne->ID;
        $container1->write();

        $container2 = new SearchUpdaterTest_Container();
        $container2->HasOneObjectID = $hasOne->ID;
        $container2->write();

        $container3 = new SearchUpdaterTest_Container();
        $container3->HasOneObjectID = $alternateHasOne->ID;
        $container3->write();

        // Check the default "writing a document updates the document"
        SearchUpdater::flush_dirty_indexes();

        $added = self::$index->getAdded(['ID']);
        // Some databases don't output $added in a consistent order; that's okay
        usort($added, function ($a, $b) {
            return $a['ID']-$b['ID'];
        });

        $this->assertEquals([
            ['ID' => $container1->ID],
            ['ID' => $container2->ID],
            ['ID' => $container3->ID],
        ], $added);

        // Check writing a has_one tracks back to the origin documents

        self::$index->reset();

        $hasOne->Field1 = "Updated";
        $hasOne->write();

        SearchUpdater::flush_dirty_indexes();
        $added = self::$index->getAdded(['ID']);

        // Some databases don't output $added in a consistent order; that's okay
        usort($added, function ($a, $b) {
            return $a['ID']-$b['ID'];
        });

        $this->assertEquals([
            ['ID' => $container1->ID],
            ['ID' => $container2->ID],
        ], $added);

        // Check updating an unrelated field doesn't track back

        self::$index->reset();

        $hasOne->Field2 = "Updated";
        $hasOne->write();

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals([], self::$index->getAdded(['ID']));

        // Check writing a has_one tracks back to the origin documents

        self::$index->reset();

        $alternateHasOne->Field1= "Updated";
        $alternateHasOne->write();

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals([
            ['ID' => $container3->ID],
        ], self::$index->getAdded(['ID']));
    }

    public function testHasManyHook()
    {
        $classesToSkip = [SearchUpdaterTest_Container::class];
        Config::modify()->set(SearchableService::class, 'indexing_canview_exclude_classes', $classesToSkip);

        $container1 = new SearchUpdaterTest_Container();
        $container1->write();

        $container2 = new SearchUpdaterTest_Container();
        $container2->write();

        //self::$index->reset();
        //SearchUpdater::clear_dirty_indexes();

        $hasMany1 = new SearchUpdaterTest_HasMany();
        $hasMany1->HasManyContainerID = $container1->ID;
        $hasMany1->write();

        $hasMany2 = new SearchUpdaterTest_HasMany();
        $hasMany2->HasManyContainerID = $container1->ID;
        $hasMany2->write();

        SearchUpdater::flush_dirty_indexes();

        $this->assertEquals([
            ['ID' => $container1->ID],
            ['ID' => $container2->ID],
        ], self::$index->getAdded(['ID']));

        self::$index->reset();

        $hasMany1->Field1 = 'Updated';
        $hasMany1->write();

        $hasMany2->Field1 = 'Updated';
        $hasMany2->write();

        SearchUpdater::flush_dirty_indexes();
        $this->assertEquals([
            ['ID' => $container1->ID],
        ], self::$index->getAdded(['ID']));
    }
}
