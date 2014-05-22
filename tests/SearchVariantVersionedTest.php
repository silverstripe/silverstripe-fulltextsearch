<?php

class SearchVariantVersionedTest_Item extends SiteTree {
	// TODO: Currently theres a failure if you addClass a non-table class
	private static $db = array(
		'TestText' => 'Varchar'
	);
}

class SearchVariantVersionedTest_Index extends SearchIndex_Recording {
	function init() {
		$this->addClass('SearchVariantVersionedTest_Item');
		$this->addFilterField('TestText');
	}
}

class SearchVariantVersionedTest_IndexNoStage extends SearchIndex_Recording {
	function init() {
		$this->addClass('SearchVariantVersionedTest_Item');
		$this->addFilterField('TestText');
		$this->excludeVariantState(array('SearchVariantVersioned' => 'Stage'));
	}
}

class SearchVariantVersionedTest extends SapphireTest {

	private static $index = null;

	function setUp() {
		parent::setUp();

		// Check versioned available
		if(!class_exists('Versioned')) {
			return $this->markTestSkipped('The versioned decorator is not installed');
		}

		if (self::$index === null) self::$index = singleton('SearchVariantVersionedTest_Index');

		SearchUpdater::bind_manipulation_capture();

		Config::nest();

		Config::inst()->update('Injector', 'SearchUpdateProcessor', array(
			'class' => 'SearchUpdateImmediateProcessor'
		));

		FullTextSearch::force_index_list(self::$index);
		SearchUpdater::clear_dirty_indexes();
	}

	function tearDown() {
		Config::unnest();

		parent::tearDown();
	}

	function testPublishing() {
		// Check that write updates Stage
		
		$item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
		$item->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID', '_versionedstage')), array(
			array('ID' => $item->ID, '_versionedstage' => 'Stage')
		));

		// Check that publish updates Live

		self::$index->reset();

		$item->publish("Stage", "Live");

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID', '_versionedstage')), array(
			array('ID' => $item->ID, '_versionedstage' => 'Live')
		));

		// Just update a SiteTree field, and check it updates Stage

		self::$index->reset();

		$item->Title = "Pow!";
		$item->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID', '_versionedstage')), array(
			array('ID' => $item->ID, '_versionedstage' => 'Stage')
		));
	}

	function testExcludeVariantState() {
		$index = singleton('SearchVariantVersionedTest_IndexNoStage');
		FullTextSearch::force_index_list($index);

		// Check that write doesn't update stage
		$item = new SearchVariantVersionedTest_Item(array('TestText' => 'Foo'));
		$item->write();
		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals($index->getAdded(array('ID', '_versionedstage')), array());

		// Check that publish updates Live
		$index->reset();
		$item->publish("Stage", "Live");
		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals($index->getAdded(array('ID', '_versionedstage')), array(
			array('ID' => $item->ID, '_versionedstage' => 'Live')
		));
	}
}
