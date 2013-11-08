<?php
class SolrIndexTest extends SapphireTest {

	function setUpOnce() {
		parent::setUpOnce();

		if (class_exists('Phockito')) Phockito::include_hamcrest();
	}

	function setUp() {
		if (!class_exists('Phockito')) {
			$this->markTestSkipped("These tests need the Phockito module installed to run");
			$this->skipTest = true;
		}

		parent::setUp();
	}

	function testBoost() {
		$serviceMock = $this->getServiceMock();
		Phockito::when($serviceMock)->search(anything(), anything(), anything(), anything(), anything())->return($this->getFakeRawSolrResponse());

		$index = new SolrIndexTest_FakeIndex();
		$index->setService($serviceMock);

		$query = new SearchQuery();
		$query->search(
			'term', 
			null, 
			array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
		);
		$index->search($query);

		Phockito::verify($serviceMock)->search('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)', anything(), anything(), anything(), anything());
	}

	function testIndexExcludesNullValues() {
		$serviceMock = $this->getServiceMock();
		$index = new SolrIndexTest_FakeIndex();
		$index->setService($serviceMock);		
		$obj = new SearchUpdaterTest_Container();

		$obj->Field1 = 'Field1 val';
		$obj->Field2 = null;
		$obj->MyDate = null;
		$docs = $index->add($obj);
		$value = $docs[0]->getField('SearchUpdaterTest_Container_Field1');
		$this->assertEquals('Field1 val', $value['value'], 'Writes non-NULL string fields');
		$value = $docs[0]->getField('SearchUpdaterTest_Container_Field2');
		$this->assertFalse($value, 'Ignores string fields if they are NULL');
		$value = $docs[0]->getField('SearchUpdaterTest_Container_MyDate');
		$this->assertFalse($value, 'Ignores date fields if they are NULL');

		$obj->MyDate = '2010-12-30';
		$docs = $index->add($obj);
		$value = $docs[0]->getField('SearchUpdaterTest_Container_MyDate');
		$this->assertEquals('2010-12-30T00:00:00Z', $value['value'], 'Writes non-NULL dates');
	}

	function testAddFieldExtraOptions() {
		Config::inst()->nest();
		Config::inst()->update('Director', 'environment_type', 'live'); // dev mode sets stored=true for everything

		$index = new SolrIndexTest_FakeIndex();

		$defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
		$defField1 = $defs->xpath('field[@name="SearchUpdaterTest_Container_Field1"]');
		$this->assertEquals((string)$defField1[0]['stored'], 'false');

		$index->addFilterField('Field1', null, array('stored' => 'true'));
		$defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
		$defField1 = $defs->xpath('field[@name="SearchUpdaterTest_Container_Field1"]');
		$this->assertEquals((string)$defField1[0]['stored'], 'true');

		Config::inst()->unnest();
	}

	function testAddAnalyzer() {
		$index = new SolrIndexTest_FakeIndex();

		$defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
		$defField1 = $defs->xpath('field[@name="SearchUpdaterTest_Container_Field1"]');
		$analyzers = $defField1[0]->analyzer;
		$this->assertFalse((bool)$analyzers);

		$index->addAnalyzer('Field1', 'charFilter', array('class' => 'solr.HTMLStripCharFilterFactory'));
		$defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
		$defField1 = $defs->xpath('field[@name="SearchUpdaterTest_Container_Field1"]');
		$analyzers = $defField1[0]->analyzer;
		$this->assertTrue((bool)$analyzers);
		$this->assertEquals('solr.HTMLStripCharFilterFactory', $analyzers[0]->charFilter[0]['class']);
	}

	function testAddCopyField() {
		$index = new SolrIndexTest_FakeIndex();		
		$index->addCopyField('sourceField', 'destField');

		$defs = simplexml_load_string('<fields>' . $index->getCopyFieldDefinitions() . '</fields>');
		$copyField = $defs->xpath('copyField');

		$this->assertEquals('sourceField', $copyField[0]['source']);
		$this->assertEquals('destField', $copyField[0]['dest']);
	}

	protected function getServiceMock() {
		return Phockito::mock('Solr3Service');
	}

	protected function getServiceSpy() {
		$serviceSpy = Phockito::spy('Solr3Service');
		Phockito::when($serviceSpy)->_sendRawPost()->return($this->getFakeRawSolrResponse());

		return $serviceSpy;
	}

	protected function getFakeRawSolrResponse() {
		return new Apache_Solr_Response(
			new Apache_Solr_HttpTransport_Response(
				null,
				null,
				'{}'
			)
		);
	}
}

class SolrIndexTest_FakeIndex extends SolrIndex {
	function init() {
		$this->addClass('SearchUpdaterTest_Container');

		$this->addFilterField('Field1');
		$this->addFilterField('MyDate', 'Date');
		$this->addFilterField('HasOneObject.Field1');
		$this->addFilterField('HasManyObjects.Field1');
	}
}