<?php

class SearchUpdaterTest_Container extends DataObject {
	static $db = array(
		'Field1' => 'Varchar',
		'Field2' => 'Varchar'
	);

	static $has_one = array(
		'HasOneObject' => 'SearchUpdaterTest_HasOne'
	);

	static $has_many = array(
		'HasManyObjects' => 'SearchUpdaterTest_HasMany'
	);
}

class SearchUpdaterTest_HasOne extends DataObject {
	static $db = array(
		'Field1' => 'Varchar',
		'Field2' => 'Varchar'
	);

	static $has_many = array(
		'HasManyContainers' => 'SearchUpdaterTest_Container'
	);
}

class SearchUpdaterTest_HasMany extends DataObject {
	static $db = array(
		'Field1' => 'Varchar',
		'Field2' => 'Varchar'
	);

	static $has_one = array(
		'HasManyContainer' => 'SearchUpdaterTest_Container'
	);
}

class SearchUpdaterTest_Index extends SearchIndex_Recording {
	function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}

class SearchUpdaterTest extends SapphireTest {

	private static $index = null;
	
	function setUp() {
		parent::setUp();

		if (self::$index === null) self::$index = singleton(get_class($this).'_Index');
		else self::$index->reset();

		SearchUpdater::bind_manipulation_capture();

		FullTextSearch::force_index_list(self::$index);
		SearchUpdater::clear_dirty_indexes();
	}

	function testBasic() {
		$item = new SearchUpdaterTest_Container();
		$item->write();

		// TODO: Make sure changing field1 updates item.
		// TODO: Get updating just field2 to not update item (maybe not possible - variants complicate)
	}

	function testHasOneHook() {
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
		$this->assertEquals(self::$index->getAdded(array('ID')), array(
			array('ID' => $container1->ID),
			array('ID' => $container2->ID),
			array('ID' => $container3->ID)
		));

		// Check writing a has_one tracks back to the origin documents

		self::$index->reset();

		$hasOne->Field1 = "Updated";
		$hasOne->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID')), array(
			array('ID' => $container1->ID),
			array('ID' => $container2->ID)
		));

		// Check updating an unrelated field doesn't track back

		self::$index->reset();

		$hasOne->Field2 = "Updated";
		$hasOne->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID')), array());

		// Check writing a has_one tracks back to the origin documents

		self::$index->reset();

		$alternateHasOne->Field1= "Updated";
		$alternateHasOne->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID')), array(
			array('ID' => $container3->ID)
		));
	}

	function testHasManyHook() {
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

		$this->assertEquals(self::$index->getAdded(array('ID')), array(
			array('ID' => $container1->ID),
			array('ID' => $container2->ID)
		));

		self::$index->reset();

		$hasMany1->Field1 = 'Updated';
		$hasMany1->write();

		$hasMany2->Field1 = 'Updated';
		$hasMany2->write();

		SearchUpdater::flush_dirty_indexes();
		$this->assertEquals(self::$index->getAdded(array('ID')), array(
			array('ID' => $container1->ID)
		));
	}
}
