<?php
class SolrIndexTest extends SapphireTest
{
    public function setUpOnce()
    {
        parent::setUpOnce();

        if (class_exists('Phockito')) {
            Phockito::include_hamcrest(false);
        }
    }

    public function setUp()
    {
        parent::setUp();

        if (!class_exists('Phockito')) {
            $this->markTestSkipped("These tests need the Phockito module installed to run");
            $this->skipTest = true;
        }
    }

    public function testFieldDataHasOne()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('HasOneObject.Field1');
        $data = $data['SearchUpdaterTest_Container_HasOneObject_Field1'];

        $this->assertEquals('SearchUpdaterTest_Container', $data['origin']);
        $this->assertEquals('SearchUpdaterTest_Container', $data['base']);
        $this->assertEquals('SearchUpdaterTest_HasOne', $data['class']);
    }

    public function testFieldDataHasMany()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('HasManyObjects.Field1');
        $data = $data['SearchUpdaterTest_Container_HasManyObjects_Field1'];

        $this->assertEquals('SearchUpdaterTest_Container', $data['origin']);
        $this->assertEquals('SearchUpdaterTest_Container', $data['base']);
        $this->assertEquals('SearchUpdaterTest_HasMany', $data['class']);
    }

    public function testFieldDataManyMany()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');
        $data = $data['SearchUpdaterTest_Container_ManyManyObjects_Field1'];

        $this->assertEquals('SearchUpdaterTest_Container', $data['origin']);
        $this->assertEquals('SearchUpdaterTest_Container', $data['base']);
        $this->assertEquals('SearchUpdaterTest_ManyMany', $data['class']);
    }

    public function testFieldDataAmbiguousHasMany()
    {
        $index = new SolrIndexTest_AmbiguousRelationIndex();
        $data = $index->fieldData('HasManyObjects.Field1');

        $this->assertArrayHasKey('SearchUpdaterTest_Container_HasManyObjects_Field1', $data);
        $this->assertArrayHasKey('SearchUpdaterTest_OtherContainer_HasManyObjects_Field1', $data);

        $dataContainer = $data['SearchUpdaterTest_Container_HasManyObjects_Field1'];
        $this->assertEquals($dataContainer['origin'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['base'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['class'], 'SearchUpdaterTest_HasMany');

        $dataOtherContainer = $data['SearchUpdaterTest_OtherContainer_HasManyObjects_Field1'];
        $this->assertEquals($dataOtherContainer['origin'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['base'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['class'], 'SearchUpdaterTest_HasMany');
    }

    public function testFieldDataAmbiguousManyMany()
    {
        $index = new SolrIndexTest_AmbiguousRelationIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');

        $this->assertArrayHasKey('SearchUpdaterTest_Container_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey('SearchUpdaterTest_OtherContainer_ManyManyObjects_Field1', $data);

        $dataContainer = $data['SearchUpdaterTest_Container_ManyManyObjects_Field1'];
        $this->assertEquals($dataContainer['origin'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['base'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['class'], 'SearchUpdaterTest_ManyMany');

        $dataOtherContainer = $data['SearchUpdaterTest_OtherContainer_ManyManyObjects_Field1'];
        $this->assertEquals($dataOtherContainer['origin'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['base'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['class'], 'SearchUpdaterTest_ManyMany');
    }

    public function testFieldDataAmbiguousManyManyInherited()
    {
        $index = new SolrIndexTest_AmbiguousRelationInheritedIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');

        $this->assertArrayHasKey('SearchUpdaterTest_Container_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey('SearchUpdaterTest_OtherContainer_ManyManyObjects_Field1', $data);
        $this->assertArrayNotHasKey('SearchUpdaterTest_ExtendedContainer_ManyManyObjects_Field1', $data);

        $dataContainer = $data['SearchUpdaterTest_Container_ManyManyObjects_Field1'];
        $this->assertEquals($dataContainer['origin'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['base'], 'SearchUpdaterTest_Container');
        $this->assertEquals($dataContainer['class'], 'SearchUpdaterTest_ManyMany');

        $dataOtherContainer = $data['SearchUpdaterTest_OtherContainer_ManyManyObjects_Field1'];
        $this->assertEquals($dataOtherContainer['origin'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['base'], 'SearchUpdaterTest_OtherContainer');
        $this->assertEquals($dataOtherContainer['class'], 'SearchUpdaterTest_ManyMany');
    }

    /**
     * Test boosting on SearchQuery
     */
    public function testBoostedQuery()
    {
        $serviceMock = $this->getServiceMock();
        Phockito::when($serviceMock)
            ->search(
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything()
            )->return($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        $query = new SearchQuery();
        $query->search(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query);

        Phockito::verify($serviceMock)
            ->search(
                '+(Field1:term^1.5 OR HasOneObject_Field1:term^3)',
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything()
            );
    }

    /**
     * Test boosting on field schema (via queried fields parameter)
     */
    public function testBoostedField()
    {
        $serviceMock = $this->getServiceMock();
        Phockito::when($serviceMock)
            ->search(
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything()
            )->return($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_BoostedIndex();
        $index->setService($serviceMock);

        $query = new SearchQuery();
        $query->search('term');
        $index->search($query);

        // Ensure matcher contains correct boost in 'qf' parameter
        $matcher = new Hamcrest_Array_IsArrayContainingKeyValuePair(
            new Hamcrest_Core_IsEqual('qf'),
            new Hamcrest_Core_IsEqual('SearchUpdaterTest_Container_Field1^1.5 SearchUpdaterTest_Container_Field2^2.1 _text')
        );
        Phockito::verify($serviceMock)
            ->search(
                '+term',
                \Hamcrest_Matchers::anything(),
                \Hamcrest_Matchers::anything(),
                $matcher,
                \Hamcrest_Matchers::anything()
            );
    }

    public function testHighlightQueryOnBoost()
    {
        $serviceMock = $this->getServiceMock();
        Phockito::when($serviceMock)->search(
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything()
        )->return($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        // Search without highlighting
        $query = new SearchQuery();
        $query->search(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query);
        Phockito::verify(
            $serviceMock)->search(
            '+(Field1:term^1.5 OR HasOneObject_Field1:term^3)',
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::not(\Hamcrest_Matchers::hasKeyInArray('hl.q')),
            \Hamcrest_Matchers::anything()
        );

        // Search with highlighting
        $query = new SearchQuery();
        $query->search(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query, -1, -1, array('hl' => true));
        Phockito::verify(
            $serviceMock)->search(
            '+(Field1:term^1.5 OR HasOneObject_Field1:term^3)',
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::anything(),
            \Hamcrest_Matchers::hasKeyInArray('hl.q'),
            \Hamcrest_Matchers::anything()
        );
    }

    public function testIndexExcludesNullValues()
    {
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

    public function testAddFieldExtraOptions()
    {
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

    public function testAddAnalyzer()
    {
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

    public function testAddCopyField()
    {
        $index = new SolrIndexTest_FakeIndex();
        $index->addCopyField('sourceField', 'destField');

        $defs = simplexml_load_string('<fields>' . $index->getCopyFieldDefinitions() . '</fields>');
        $copyField = $defs->xpath('copyField');

        $this->assertEquals('sourceField', $copyField[0]['source']);
        $this->assertEquals('destField', $copyField[0]['dest']);
    }

    /**
     * Tests the setting of the 'stored' flag
     */
    public function testStoredFields()
    {
        // Test two fields
        $index = new SolrIndexTest_FakeIndex2();
        $index->addStoredField('Field1');
        $index->addFulltextField('Field2');
        $schema = $index->getFieldDefinitions();
        $this->assertContains(
            "<field name='SearchUpdaterTest_Container_Field1' type='text' indexed='true' stored='true'",
            $schema
        );
        $this->assertContains(
            "<field name='SearchUpdaterTest_Container_Field2' type='text' indexed='true' stored='false'",
            $schema
        );

        // Test with addAllFulltextFields
        $index2 = new SolrIndexTest_FakeIndex2();
        $index2->addAllFulltextFields();
        $index2->addStoredField('Field2');
        $schema2 = $index2->getFieldDefinitions();
        $this->assertContains(
            "<field name='SearchUpdaterTest_Container_Field1' type='text' indexed='true' stored='false'",
            $schema2
        );
        $this->assertContains(
            "<field name='SearchUpdaterTest_Container_Field2' type='text' indexed='true' stored='true'",
            $schema2
        );
    }

    /**
     * @return Solr3Service
     */
    protected function getServiceMock()
    {
        return Phockito::mock('Solr3Service');
    }

    protected function getServiceSpy()
    {
        $serviceSpy = Phockito::spy('Solr3Service');
        Phockito::when($serviceSpy)->_sendRawPost()->return($this->getFakeRawSolrResponse());

        return $serviceSpy;
    }

    protected function getFakeRawSolrResponse()
    {
        return new Apache_Solr_Response(
            new Apache_Solr_HttpTransport_Response(
                null,
                null,
                '{}'
            )
        );
    }
}

class SolrIndexTest_FakeIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');

        $this->addFilterField('Field1');
        $this->addFilterField('MyDate', 'Date');
        $this->addFilterField('HasOneObject.Field1');
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}


class SolrIndexTest_FakeIndex2 extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        $this->addFilterField('MyDate', 'Date');
        $this->addFilterField('HasOneObject.Field1');
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}


class SolrIndexTest_BoostedIndex extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        $this->addAllFulltextFields();
        $this->setFieldBoosting('SearchUpdaterTest_Container_Field1', 1.5);
        $this->addBoostedField('Field2', null, array(), 2.1);
    }
}

class SolrIndexTest_AmbiguousRelationIndex extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        $this->addClass('SearchUpdaterTest_OtherContainer');

        // These relationships exist on both classes
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}

class SolrIndexTest_AmbiguousRelationInheritedIndex extends SolrIndex
{
    protected function getStoredDefault()
    {
        // Override isDev defaulting to stored
        return 'false';
    }

    public function init()
    {
        $this->addClass('SearchUpdaterTest_Container');
        // this one has not the relation defined in it's class but is rather inherited from parent
        // note that even if we do not include it's parent class the fields will be properly added
        $this->addClass('SearchUpdaterTest_ExtendedContainer');

        // These relationships exist on both classes
        $this->addFilterField('HasManyObjects.Field1');
        $this->addFilterField('ManyManyObjects.Field1');
    }
}
