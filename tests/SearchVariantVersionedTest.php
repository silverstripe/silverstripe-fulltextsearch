<?php

class SearchVariantVersionedTest_Item extends SiteTree {
	// TODO: Currently theres a failure if you addClass a non-table class
	static $db = array(
		'TestText' => 'Varchar'
	);
}

class SearchVariantVersionedTest_Index extends SearchIndex_Recording {
	function init() {
		$this->addClass('SearchVariantVersionedTest_Item');
		$this->addFilterField('TestText');
	}
}

class SearchVariantVersionedTest extends SapphireTest {

	private static $index = null;

	function setUp() {
		parent::setUp();
		
		// Check versioned installed (something is very wrong if it isn't)
		if(!class_exists('Versioned')) {
			user_error("Versioned class not available. Something is wrong with your SilverStripe install as \"Versioned\" is core sapphire functionality");
			return false;
		}	

		if (self::$index === null) self::$index = singleton('SearchVariantVersionedTest_Index');

		FullTextSearch::force_index_list(self::$index);
		SearchUpdater::clear_dirty_indexes();
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
			array('ID' => $item->ID, '_versionedstage' => 'Stage3')
		));
	}
}
