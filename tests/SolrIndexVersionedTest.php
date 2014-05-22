<?php

if (class_exists('Phockito')) Phockito::include_hamcrest();

class SolrIndexVersionedTest extends SapphireTest {
	
	protected $oldMode = null;
	
	protected static $index = null;
	
	protected $extraDataObjects = array(
		'SearchVariantVersionedTest_Item'
	);

	public function setUp() {

		parent::setUp();
		
		if (!class_exists('Phockito')) {
			$this->skipTest = true;
			return $this->markTestSkipped("These tests need the Phockito module installed to run");
		}

		// Check versioned available
		if(!class_exists('Versioned')) {
			$this->skipTest = true;
			return $this->markTestSkipped('The versioned decorator is not installed');
		}

		if (self::$index === null) self::$index = singleton('SolrVersionedTest_Index');

		SearchUpdater::bind_manipulation_capture();

		Config::nest();

		Config::inst()->update('Injector', 'SearchUpdateProcessor', array(
			'class' => 'SearchUpdateImmediateProcessor'
		));

		FullTextSearch::force_index_list(self::$index);
		SearchUpdater::clear_dirty_indexes();
		
		$this->oldMode = Versioned::get_reading_mode();
		Versioned::reading_stage('Stage');
	}
	
	public function tearDown() {
		Versioned::set_reading_mode($this->oldMode);
		Config::unnest();
		parent::tearDown();
	}

	protected function getServiceMock() {
		return Phockito::mock('Solr3Service');
	}
	
	protected function getExpectedDocumentId($id, $stage) {
		// Prevent subsites from breaking tests
		$subsites = class_exists('Subsite') ? '"SearchVariantSubsites":"0",' : '';
		return $id.'-SiteTree-{'.$subsites.'"SearchVariantVersioned":"'.$stage.'"}';
	}
	
	public function testPublishing() {
		
		// Setup mocks
		$serviceMock = $this->getServiceMock();
		self::$index->setService($serviceMock);
		
		// Check that write updates Stage
		Versioned::reading_stage('Stage');
		Phockito::reset($serviceMock);
		$item = new SearchVariantVersionedTest_Item(array('Title' => 'Foo'));
		$item->write();
		SearchUpdater::flush_dirty_indexes();
		$doc = new SolrDocumentMatcher(array(
			'_documentid' => $this->getExpectedDocumentId($item->ID, 'Stage'),
			'ClassName' => 'SearchVariantVersionedTest_Item'
		));
		Phockito::verify($serviceMock)->addDocument($doc);
		
		// Check that write updates Live
		Versioned::reading_stage('Stage');
		Phockito::reset($serviceMock);
		$item = new SearchVariantVersionedTest_Item(array('Title' => 'Bar'));
		$item->write();
		$item->publish('Stage', 'Live');
		SearchUpdater::flush_dirty_indexes();
		$doc = new SolrDocumentMatcher(array(
			'_documentid' => $this->getExpectedDocumentId($item->ID, 'Live'),
			'ClassName' => 'SearchVariantVersionedTest_Item'
		));
		Phockito::verify($serviceMock)->addDocument($doc);
	}
	
	public function testDelete() {
		
		// Setup mocks
		$serviceMock = $this->getServiceMock();
		self::$index->setService($serviceMock);
		
		// Delete the live record (not the stage)
		Versioned::reading_stage('Stage');
		Phockito::reset($serviceMock);
		$item = new SearchVariantVersionedTest_Item(array('Title' => 'Too'));
		$item->write();
		$item->publish('Stage', 'Live');
		Versioned::reading_stage('Live');
		$id = $item->ID;
		$item->delete();
		SearchUpdater::flush_dirty_indexes();
		Phockito::verify($serviceMock, 1)
			->deleteById($this->getExpectedDocumentId($id, 'Live'));
		Phockito::verify($serviceMock, 0)
			->deleteById($this->getExpectedDocumentId($id, 'Stage'));
		
		// Delete the stage record
		Versioned::reading_stage('Stage');
		Phockito::reset($serviceMock);
		$item = new SearchVariantVersionedTest_Item(array('Title' => 'Too'));
		$item->write();
		$item->publish('Stage', 'Live');
		$id = $item->ID;
		$item->delete();
		SearchUpdater::flush_dirty_indexes();
		Phockito::verify($serviceMock, 1)
			->deleteById($this->getExpectedDocumentId($id, 'Stage'));
		Phockito::verify($serviceMock, 0)
			->deleteById($this->getExpectedDocumentId($id, 'Live'));
	}
}


class SolrVersionedTest_Index extends SolrIndex {
	function init() {
		$this->addClass('SearchVariantVersionedTest_Item');
		$this->addFilterField('TestText');
	}
}


class SolrDocumentMatcher extends Hamcrest_BaseMatcher {
	
	protected $properties;
	
	public function __construct($properties) {
		$this->properties = $properties;
	}

	public function describeTo(\Hamcrest_Description $description) {
		$description->appendText('Apache_Solr_Document with properties '.var_export($this->properties, true));
	}

	public function matches($item) {
		
		if(! ($item instanceof Apache_Solr_Document)) return false;
		
		foreach($this->properties as $key => $value) {
			if($item->{$key} != $value) return false;
		}
		
		return true;
	}

}
