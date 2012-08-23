<?php
class SolrIndexTest extends SapphireTest {

	function setUpOnce() {
		parent::setUpOnce();

		Phockito::include_hamcrest();
	}
	
	function testBoost() {
		$serviceMock = $this->getServiceMock();
		$index = new SolrIndexTest_FakeIndex();
		$index->setService($serviceMock);

		$query = new SearchQuery();
		$query->search(
			'term', 
			null, 
			array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
		);
		$index->search($query);

		Phockito::verify($serviceMock)->search(
			'+(Field1:term^1.5 OR HasOneObject_Field1:term^3)',
			anything(), anything(), anything(), anything()
		);
	}

	protected function getServiceMock() {
		$serviceMock = Phockito::mock('SolrService');
		$fakeResponse = new Apache_Solr_Response(new Apache_Solr_HttpTransport_Response(null, null, null));
		Phockito::when($serviceMock)
			->search(anything(), anything(), anything(), anything(), anything())
			->return($fakeResponse);
		return $serviceMock;
	}

}

class SolrIndexTest_FakeIndex extends SolrIndex {
	function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}