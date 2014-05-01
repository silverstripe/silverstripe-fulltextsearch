<?php

if (class_exists('Phockito')) Phockito::include_hamcrest();

/**
 * Description of SolrQueryFilterTest
 *
 * @author dmooyman
 */
class SolrQueryFilterTest extends SapphireTest {
	
	protected $extraDataObjects = array(
		'SearchUpdaterTest_Container'
	);
	
	protected static $fixture_file = 'SolrQueryFilterTest.yml';

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
		return singleton('SolrQueryFilterTest_BaseIndex')->getFilterCache();
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
		$index = singleton('SolrQueryFilterTest_CachedIndex');
		$index->setService($service);
		
		// Do initial search (note that 'Get Both' is never called)
		$this->checkResults($index, $item1, $item2);
		
		// Change behaviour of service to see if cached results are still returned
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item2),
			'+Get +Second' => array($item3),
			'+Get +Both' => array($item2, $item3)
		));
		$index->setService($service);
		$this->checkResults($index, $item1, $item2);
		
		// Search for 'Get Both' to make sure an uncached hit still gets through
		$bothResult = $this->doSearch($index, 'Get Both');
		$this->assertEquals(2, $bothResult->Matches->count());
		$this->assertEquals($item2->ID, $bothResult->Matches->first()->ID);
		$this->assertEquals($item3->ID, $bothResult->Matches->last()->ID);
	}
	
	public function testRateLimiting() {
		
		// Setup mocks
		$item1 = $this->objFromFixture('SearchUpdaterTest_Container', 'item1');
		$item2 = $this->objFromFixture('SearchUpdaterTest_Container', 'item2');
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item1),
			'+Get +Second' => array($item2)
		));
		$index = singleton('SolrQueryFilterTest_RateIndex');
		$index->setService($service);
		
		// Ensure initial request doesn't hit the rate limit
		$result1 = $this->doSearch($index, 'Just First');
		$this->assertEquals(1, $result1->Matches->count());
		$this->assertEquals($item1->ID, $result1->Matches->first()->ID);
		
		// Subsequent requests should hit the cooldown limit
		$exception = null;
		try {
			$this->doSearch($index, 'Get Second');
		} catch(SS_HTTPResponse_Exception $ex) {
			$exception = $ex;
		}
		$this->assertInstanceOf('SS_HTTPResponse_Exception', $exception);
		$this->assertEquals(429, $exception->getResponse()->getStatusCode());
		$this->assertGreaterThan(0, $exception->getResponse()->getHeader('Retry-After'));
	}
	
	/**
	 * Test filter with both cache and rate limit applied
	 */
	public function testCachedRateLimit() {
		
		// Setup mocks
		$item1 = $this->objFromFixture('SearchUpdaterTest_Container', 'item1');
		$item2 = $this->objFromFixture('SearchUpdaterTest_Container', 'item2');
		$item3 = $this->objFromFixture('SearchUpdaterTest_Container', 'item3');
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item1),
			'+Get +Second' => array($item2),
			'+The +Third' => array($item3)
		));
		$index = singleton('SolrQueryFilterTest_CachedRateIndex');
		$index->setService($service);
		
		// Ensure initial request doesn't hit the rate limit
		$result1 = $this->doSearch($index, 'Just First');
		$this->assertEquals(1, $result1->Matches->count());
		$this->assertEquals($item1->ID, $result1->Matches->first()->ID);
		
		// Subsequent requests should hit the cooldown limit
		$exception = null;
		try {
			$this->doSearch($index, 'Get Second');
		} catch(SS_HTTPResponse_Exception $ex) {
			$exception = $ex;
		}
		$this->assertInstanceOf('SS_HTTPResponse_Exception', $exception);
		$this->assertEquals(429, $exception->getResponse()->getStatusCode());
		$this->assertGreaterThan(0, $exception->getResponse()->getHeader('Retry-After'));
		
		// Test that requests to the initial service hit the cache, and therefore bypass the rate limit
		$result1 = $this->doSearch($index, 'Just First');
		$this->assertEquals(1, $result1->Matches->count());
		$this->assertEquals($item1->ID, $result1->Matches->first()->ID);
		
		// Test that this cache maintains its value even after service behavioural changes
		$service = $this->getServiceMock(array(
			'+Just +First' => array($item3),
			'+Get +Second' => array($item1),
			'+The +Third' => array($item2, $item1)
		));
		$index->setService($service);
		$result1 = $this->doSearch($index, 'Just First');
		$this->assertEquals(1, $result1->Matches->count());
		$this->assertEquals($item1->ID, $result1->Matches->first()->ID);
		
		// Rate limiting still influences requests to uncached results
		$exception = null;
		try {
			$this->doSearch($index, 'The Third');
		} catch(SS_HTTPResponse_Exception $ex) {
			$exception = $ex;
		}
		$this->assertInstanceOf('SS_HTTPResponse_Exception', $exception);
		$this->assertEquals(429, $exception->getResponse()->getStatusCode());
		$this->assertGreaterThan(0, $exception->getResponse()->getHeader('Retry-After'));
	}
}

class SolrQueryFilterTest_BaseIndex extends SolrIndex {

	public function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('MyDate', 'Date');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}

class SolrQueryFilterTest_CachedIndex extends SolrQueryFilterTest_BaseIndex {
	
	private static $rate_enabled = false;
	
	private static $cache_enabled = true;
	
	private static $cache_lifetime = 1000;
}

class SolrQueryFilterTest_RateIndex extends SolrQueryFilterTest_BaseIndex {
	
	private static $cache_enabled = false;
	
	private static $rate_enabled = true;
	
	private static $rate_byuserip = false;
	
	private static $rate_byquery = false;
	
	private static $rate_timeout = 10;
	
	private static $rate_cooldown = 10;
}


class SolrQueryFilterTest_CachedRateIndex extends SolrQueryFilterTest_BaseIndex {
	
	private static $cache_lifetime = 1000;
	
	private static $cache_enabled = true;
	
	private static $rate_enabled = true;
	
	private static $rate_byuserip = false;
	
	private static $rate_byquery = false;
	
	private static $rate_timeout = 10;
	
	private static $rate_cooldown = 10;
}
