<?php

namespace SilverStripe\FullTextSearch\Tests;

use Apache_Solr_Document;
use Page;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Services\SearchableService;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantSubsites;
use SilverStripe\FullTextSearch\Solr\Services\Solr3Service;
use SilverStripe\FullTextSearch\Solr\Services\Solr4Service;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_Container;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasOne;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_HasMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_ManyMany;
use SilverStripe\FullTextSearch\Tests\SearchUpdaterTest\SearchUpdaterTest_OtherContainer;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_AmbiguousRelationIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_AmbiguousRelationInheritedIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_BoostedIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex2;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_ShowInSearchIndex;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyPage;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectOne;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_MyDataObjectTwo;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

class SolrIndexTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        SolrIndexTest_MyPage::class,
        SolrIndexTest_MyDataObjectOne::class,
        SolrIndexTest_MyDataObjectTwo::class,
    ];

    public function testFieldDataHasOne()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('HasOneObject.Field1');

        $data = $data[SearchUpdaterTest_Container::class . '_HasOneObject_Field1'];

        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_HasOne::class, $data['class']);
    }

    public function testFieldDataHasMany()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('HasManyObjects.Field1');
        $data = $data[SearchUpdaterTest_Container::class . '_HasManyObjects_Field1'];

        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $data['class']);
    }

    public function testFieldDataManyMany()
    {
        $index = new SolrIndexTest_FakeIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');
        $data = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];

        $this->assertEquals(SearchUpdaterTest_Container::class, $data['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $data['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $data['class']);
    }

    public function testFieldDataAmbiguousHasMany()
    {
        $index = new SolrIndexTest_AmbiguousRelationIndex();
        $data = $index->fieldData('HasManyObjects.Field1');

        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_HasManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_HasManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_HasManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_HasManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_HasMany::class, $dataOtherContainer['class']);
    }

    public function testFieldDataAmbiguousManyMany()
    {
        $index = new SolrIndexTest_AmbiguousRelationIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');

        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataOtherContainer['class']);
    }

    public function testFieldDataAmbiguousManyManyInherited()
    {
        $index = new SolrIndexTest_AmbiguousRelationInheritedIndex();
        $data = $index->fieldData('ManyManyObjects.Field1');

        $this->assertArrayHasKey(SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayHasKey(SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1', $data);
        $this->assertArrayNotHasKey(SearchUpdaterTest_ExtendedContainer::class . '_ManyManyObjects_Field1', $data);

        $dataContainer = $data[SearchUpdaterTest_Container::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_Container::class, $dataContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataContainer['class']);

        $dataOtherContainer = $data[SearchUpdaterTest_OtherContainer::class . '_ManyManyObjects_Field1'];
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['origin']);
        $this->assertEquals(SearchUpdaterTest_OtherContainer::class, $dataOtherContainer['base']);
        $this->assertEquals(SearchUpdaterTest_ManyMany::class, $dataOtherContainer['class']);
    }

    /**
     * Test boosting on SearchQuery
     */
    public function testBoostedQuery()
    {
        /** @var Solr3Service|MockObject $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->setMethods(['search'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )->willReturn($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query);
    }

    /**
     * Test boosting on field schema (via queried fields parameter)
     */
    public function testBoostedField()
    {
        if (class_exists(Subsite::class)) {
            Config::modify()->set(SearchVariantSubsites::class, 'enabled', false);
        }

        /** @var Solr3Service|MockObject $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->setMethods(['search'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('+term'),
                $this->anything(),
                $this->anything(),
                $this->equalTo([
                    'qf' => SearchUpdaterTest_Container::class . '_Field1^1.5 '
                        . SearchUpdaterTest_Container::class . '_Field2^2.1 _text',
                    'fq' => '',
                ]),
                $this->anything()
            )->willReturn($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_BoostedIndex();
        $index->setService($serviceMock);

        $query = new SearchQuery();
        $query->addSearchTerm('term');
        $index->search($query);
    }

    public function testHighlightQueryOnBoost()
    {
        /** @var SilverStripe\FullTextSearch\Solr\Services\Solr3Service|ObjectProphecy $serviceMock */
        $serviceMock = $this->getMockBuilder(Solr3Service::class)
            ->setMethods(['search'])
            ->getMock();

        $serviceMock->expects($this->exactly(2))
            ->method('search')
            ->withConsecutive(
                [
                    $this->equalTo('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)'),
                    $this->anything(),
                    $this->anything(),
                    $this->logicalNot(
                        $this->arrayHasKey('hl.q')
                    ),
                    $this->anything()
                ],
                [
                    $this->equalTo('+(Field1:term^1.5 OR HasOneObject_Field1:term^3)'),
                    $this->anything(),
                    $this->anything(),
                    $this->arrayHasKey('hl.q'),
                    $this->anything()
                ]
            )->willReturn($this->getFakeRawSolrResponse());

        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);

        // Search without highlighting
        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query);

        // Search with highlighting
        $query = new SearchQuery();
        $query->addSearchTerm(
            'term',
            null,
            array('Field1' => 1.5, 'HasOneObject_Field1' => 3)
        );
        $index->search($query, -1, -1, array('hl' => true));
    }

    public function testIndexExcludesNullValues()
    {
        /** @var Solr3Service|ObjectProphecy $serviceMock */
        $serviceMock = $this->createMock(Solr3Service::class);
        $index = new SolrIndexTest_FakeIndex();
        $index->setService($serviceMock);
        $obj = new SearchUpdaterTest_Container();

        $obj->Field1 = 'Field1 val';
        $obj->Field2 = null;
        $obj->MyDate = null;
        $docs = $index->add($obj);
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_Field1');
        $this->assertEquals('Field1 val', $value['value'], 'Writes non-NULL string fields');
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_Field2');
        $this->assertFalse($value, 'Ignores string fields if they are NULL');
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_MyDate');
        $this->assertFalse($value, 'Ignores date fields if they are NULL');

        $obj->MyDate = '2010-12-30';
        $docs = $index->add($obj);
        $value = $docs[0]->getField(SearchUpdaterTest_Container::class . '_MyDate');
        $this->assertEquals('2010-12-30T00:00:00Z', $value['value'], 'Writes non-NULL dates');
    }

    public function testAddFieldExtraOptions()
    {
        Injector::inst()->get(Kernel::class)->setEnvironment('live');

        $index = new SolrIndexTest_FakeIndex();

        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . SearchUpdaterTest_Container::class . '_Field1"]');
        $this->assertEquals((string)$defField1[0]['stored'], 'false');

        $index->addFilterField('Field1', null, array('stored' => 'true'));
        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . SearchUpdaterTest_Container::class . '_Field1"]');
        $this->assertEquals((string)$defField1[0]['stored'], 'true');
    }

    public function testAddAnalyzer()
    {
        $index = new SolrIndexTest_FakeIndex();

        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . SearchUpdaterTest_Container::class . '_Field1"]');
        $analyzers = $defField1[0]->analyzer;
        $this->assertFalse((bool)$analyzers);

        $index->addAnalyzer('Field1', 'charFilter', array('class' => 'solr.HTMLStripCharFilterFactory'));
        $defs = simplexml_load_string('<fields>' . $index->getFieldDefinitions() . '</fields>');
        $defField1 = $defs->xpath('field[@name="' . SearchUpdaterTest_Container::class . '_Field1"]');
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
        $this->assertStringContainsString(
            "<field name='" . SearchUpdaterTest_Container::class . "_Field1' type='text' indexed='true' stored='true'",
            $schema
        );
        $this->assertStringContainsString(
            "<field name='" . SearchUpdaterTest_Container::class . "_Field2' type='text' indexed='true' stored='false'",
            $schema
        );

        // Test with addAllFulltextFields
        $index2 = new SolrIndexTest_FakeIndex2();
        $index2->addAllFulltextFields();
        $index2->addStoredField('Field2');
        $schema2 = $index2->getFieldDefinitions();
        $this->assertStringContainsString(
            "<field name='" . SearchUpdaterTest_Container::class . "_Field1' type='text' indexed='true' stored='false'",
            $schema2
        );
        $this->assertStringContainsString(
            "<field name='" . SearchUpdaterTest_Container::class . "_Field2' type='text' indexed='true' stored='true'",
            $schema2
        );
    }

    public function testSanitiseClassName()
    {
        $index = new SolrIndexTest_FakeIndex2;

        $this->assertSame(
            'SilverStripe\\\\FullTextSearch\\\\Tests\\\\SolrIndexTest',
            $index->sanitiseClassName(static::class)
        );

        $this->assertSame(
            'SilverStripe-FullTextSearch-Tests-SolrIndexTest',
            $index->sanitiseClassName(static::class, '-')
        );
    }

    public function testGetIndexName()
    {
        $index = new SolrIndexTest_FakeIndex2;
        $this->assertSame(
            'SilverStripe-FullTextSearch-Tests-SolrIndexTest-SolrIndexTest_FakeIndex2',
            $index->getIndexName()
        );
    }

    public function testGetIndexNameWithPrefixAndSuffixFromEnvironment()
    {
        $index = new SolrIndexTest_FakeIndex2;

        Environment::putEnv('SS_SOLR_INDEX_PREFIX="foo_"');
        Environment::putEnv('SS_SOLR_INDEX_SUFFIX="_bar"');

        $this->assertSame(
            'foo_SilverStripe-FullTextSearch-Tests-SolrIndexTest-SolrIndexTest_FakeIndex2_bar',
            $index->getIndexName()
        );
    }

    /**
     * Test that ShowInSearch and getShowInSearch() exclude DataObjects from being added to the index
     *
     * Note: this code path that really being tested here is SearchUpdateProcessor->prepareIndexes()
     * This code path is used for 'inlet' filtering on CMS->save()
     * The results of this will show-up in SolrIndex->_addAs()
     */
    public function testShowInSearch()
    {
        // allow anonymous users to assess draft-only content to pass canView() check (will auto-reset for next test)
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['addDocument', 'deleteById'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page true';
        $pageA->ShowInSearch = true;
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page false';
        $page->ShowInSearch = false;
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File true';
        $fileA->ShowInSearch = true;
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File false';
        $file->ShowInSearch = false;
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->ShowInSearch = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->ShowInSearch = false;
        $objOne->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $objTwoA = new SolrIndexTest_MyDataObjectTwo();
        $objTwoA->Title = 'Test MyDataObjectTwo false';
        $objTwoA->ShowInSearch = false;
        $objTwoA->write();

        // will get added
        // this class has a getShowInSearch() == true, which will override $mypage->ShowInSearch = false
        $myPageA = new SolrIndexTest_MyPage();
        $myPageA->Title = 'Test MyPage false';
        $myPageA->ShowInSearch = false;
        $myPageA->write();

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $myPageA, $fileA, $objOneA, $objTwoA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                SolrIndexTest_MyPage::class . $myPageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID,
                SolrIndexTest_MyDataObjectTwo::class . $objTwoA->ID
            ];
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        $serviceMock
            ->expects($this->exactly(5))
            ->method('addDocument')
            ->withConsecutive(
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)]
            );

        // This is what actually triggers all the solr stuff
        SearchUpdater::flush_dirty_indexes();

        // delete a solr doc by setting ShowInSearch to false
        $pageA->ShowInSearch = false;
        $pageA->write();

        $serviceMock
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->withConsecutive(
                [$this->callback(function (string $docID) use ($pageA): bool {
                    return strpos($docID ?? '', $pageA->ID . '-' . SiteTree::class) !== false;
                })]
            );

        SearchableService::singleton()->clearCache();
        SearchUpdater::flush_dirty_indexes();
    }

    /**
     * Test that canView() check is used to exclude DataObjects from being added to the index
     *
     * Note: this code path that really being tested here is SearchUpdateProcessor->prepareIndexes()
     * This code path is used for 'inlet' filtering on CMS->save()
     * The results of this will show-up in SolrIndex->_addAs()
     */
    public function testCanView()
    {
        // allow anonymous users to assess draft-only content to pass canView() check (will auto-reset for next test)
        Versioned::set_draft_site_secured(false);
        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
        Config::modify()->set(SearchableService::class, 'variant_state_draft_excluded', false);

        $serviceMock = $this->getMockBuilder(Solr4Service::class)
            ->setMethods(['addDocument', 'deleteById'])
            ->getMock();

        $index = new SolrIndexTest_ShowInSearchIndex();
        $index->setService($serviceMock);
        FullTextSearch::force_index_list($index);

        // will get added
        $pageA = new Page();
        $pageA->Title = 'Test Page Anyone';
        $pageA->CanViewType = 'Anyone';
        $pageA->write();

        // will get filtered out
        $page = new Page();
        $page->Title = 'Test Page LoggedInUsers';
        $page->CanViewType = 'LoggedInUsers';
        $page->write();

        // will get added
        $fileA = new File();
        $fileA->Title = 'Test File Anyone';
        $fileA->CanViewType = 'Anyone';
        $fileA->write();

        // will get filtered out
        $file = new File();
        $file->Title = 'Test File LoggedInUsers';
        $file->CanViewType = 'LoggedInUsers';
        $file->write();

        // will get added
        $objOneA = new SolrIndexTest_MyDataObjectOne();
        $objOneA->Title = 'Test MyDataObjectOne true';
        $objOneA->ShowInSearch = true;
        $objOneA->CanViewValue = true;
        $objOneA->write();

        // will get filtered out
        $objOne = new SolrIndexTest_MyDataObjectOne();
        $objOne->Title = 'Test MyDataObjectOne false';
        $objOne->ShowInSearch = true;
        $objOne->CanViewValue = false;
        $objOne->write();

        $callback = function (Apache_Solr_Document $doc) use ($pageA, $fileA, $objOneA): bool {
            $validKeys = [
                Page::class . $pageA->ID,
                File::class . $fileA->ID,
                SolrIndexTest_MyDataObjectOne::class . $objOneA->ID
            ];
            return in_array($this->createSolrDocKey($doc), $validKeys ?? []);
        };

        $serviceMock
            ->expects($this->exactly(3))
            ->method('addDocument')
            ->withConsecutive(
                [$this->callback($callback)],
                [$this->callback($callback)],
                [$this->callback($callback)]
            );

        // This is what actually triggers all the solr stuff
        SearchUpdater::flush_dirty_indexes();

        // delete a solr doc by setting ShowInSearch to false
        $pageA->ShowInSearch = false;
        $pageA->write();

        $serviceMock
            ->expects($this->exactly(1))
            ->method('deleteById')
            ->withConsecutive(
                [$this->callback(function (string $docID) use ($pageA): bool {
                    return strpos($docID ?? '', $pageA->ID . '-' . SiteTree::class) !== false;
                })]
            );

        SearchableService::singleton()->clearCache();
        SearchUpdater::flush_dirty_indexes();
    }

    protected function createSolrDocKey(Apache_Solr_Document $doc)
    {
        return $doc->getField('ClassName')['value'] . $doc->getField('ID')['value'];
    }

    protected function getFakeRawSolrResponse()
    {
        return new \Apache_Solr_Response(
            new \Apache_Solr_HttpTransport_Response(
                null,
                null,
                '{}'
            )
        );
    }
}
