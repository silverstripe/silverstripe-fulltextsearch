<?php

if (class_exists('Phockito')) Phockito::include_hamcrest();

/**
 * Description of SolrQueryFilterTest
 *
 * @author dmooyman
 */
class SolrCacheTest extends SapphireTest {

	protected $extraDataObjects = array(
		'SearchUpdaterTest_Container'
	);

	protected static $fixture_file = 'SolrCacheTest.yml';

	public function setUp() {
		parent::setUp();

		if (!class_exists('Phockito')) {
			$this->skipTest = true;
			return $this->markTestSkipped("These tests need the Phockito module installed to run");
		}

		// flush cache
		$cache = $this->getFilterCache();
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
	}

	protected function getFilterCache() {
		$cache = SS_Cache::factory('SolrService_Cache');
		$cache->setOption('automatic_serialization', true);
		return $cache;
	}

	protected function getServiceMock($operations) {
		$service = Phockito::mock('Solr3Service');
		foreach($operations as $input => $items) {
			$response = $this->getResponseMock($items);
			Phockito::when($service->search($input, anything(), anything(), anything(), anything()))
				->return($response);
		}
		return $service;
	}

	protected function getResponseMock($items) {
		$response = Phockito::mock('Apache_Solr_Response');
		Phockito::when($response->getHttpStatus())->return(200);
		$response->response = new stdClass();
		$response->response->numFound = count($items);
		$response->response->docs = $items;
		return $response;
	}

	/**
	 * Performs a search using the relevant text
	 * 
	 * @param SolrQueryFilterTest_BaseIndex $index
	 * @param string $text
	 * @return ArrayData
	 */
	protected function doSearch($index, $text) {
		$query = new SearchQuery();
		$query->search($text);
		return $index->search($query);
	}

	/**
	 * Helper function for testCaching()
	 * 
	 * @param SolrQueryFilterTest_BaseIndex $index
	 * @param SearchUpdaterTest_Container $first Expected results of search for 'Just First'
	 * @param SearchUpdaterTest_Container $second Expected results of search for 'Get Second'
	 */
	protected function checkResults($index, $first, $second) {
		// Cache search for first item
		$result1 = $this->doSearch($index, 'Just First');
		$this->assertEquals(1, $result1->Matches->count());
		$this->assertEquals($first->ID, $result1->Matches->first()->ID);
		$this->assertEquals('SearchUpdaterTest_Container', $result1->Matches->first()->ClassName);

		// Cache search for second item
		$result2 = $this->doSearch($index, 'Get Second');
		$this->assertEquals(1, $result2->Matches->count());
		$this->assertEquals($second->ID, $result2->Matches->first()->ID);
		$this->assertEquals('SearchUpdaterTest_Container', $result2->Matches->last()->ClassName);
	}

	public function testCaching() {
		// Setup mocks
		$item1 = $this->objFromFixture('SearchUpdaterTest_Container', 'item1');
		$item2 = $this->objFromFixture('SearchUpdaterTest_Container', 'item2');
		$item3 = $this->objFromFixture('SearchUpdaterTest_Container', 'item3');
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item1),
			'+Get +Second' => array($item2),
			'+Get +Both' => array($item1, $item2)
		));
		$index = singleton('SolrCacheTest_TestIndex');
		$index->getService()->setParent($service);

		// Do initial search (note that 'Get Both' is never called)
		$this->checkResults($index, $item1, $item2);

		// Change behaviour of service to see if cached results are still returned
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item2),
			'+Get +Second' => array($item3),
			'+Get +Both' => array($item2, $item3)
		));
		$index->getService()->setParent($service);
		$this->checkResults($index, $item1, $item2);

		// Search for 'Get Both' to make sure an uncached hit still gets through
		$bothResult = $this->doSearch($index, 'Get Both');
		$this->assertEquals(2, $bothResult->Matches->count());
		$this->assertEquals($item2->ID, $bothResult->Matches->first()->ID);
		$this->assertEquals($item3->ID, $bothResult->Matches->last()->ID);
	}
}

class SolrCacheTest_TestIndex extends SolrIndex implements TestOnly {

	public function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('MyDate', 'Date');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}
