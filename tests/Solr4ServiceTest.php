<?php

namespace SilverStripe\FullTextSearch\Tests;

use SilverStripe\FullTextSearch\Tests\Solr4ServiceTest\Solr4ServiceTest_RecordingService;
use SilverStripe\Dev\SapphireTest;

/**
 * Test solr 4.0 compatibility
 */
class Solr4ServiceTest extends SapphireTest
{
    /**
     *
     * @return Solr4ServiceTest_RecordingService
     */
    protected function getMockService()
    {
        return new Solr4ServiceTest_RecordingService();
    }
    
    protected function getMockDocument($id)
    {
        $document = new \Apache_Solr_Document();
        $document->setField('id', $id);
        $document->setField('title', "Item $id");
        return $document;
    }
    
    public function testAddDocument()
    {
        $service = $this->getMockService();
        $sent = $service->addDocument($this->getMockDocument('A'), false);
        $this->assertEquals(
            '<add overwrite="true"><doc><field name="id">A</field><field name="title">Item A</field></doc></add>',
            $sent
        );
        $sent = $service->addDocument($this->getMockDocument('B'), true);
        $this->assertEquals(
            '<add overwrite="false"><doc><field name="id">B</field><field name="title">Item B</field></doc></add>',
            $sent
        );
    }
    
    public function testAddDocuments()
    {
        $service = $this->getMockService();
        $sent = $service->addDocuments(array(
            $this->getMockDocument('C'),
            $this->getMockDocument('D')
        ), false);
        $this->assertEquals(
            '<add overwrite="true"><doc><field name="id">C</field><field name="title">Item C</field></doc><doc><field name="id">D</field><field name="title">Item D</field></doc></add>',
            $sent
        );
        $sent = $service->addDocuments(array(
            $this->getMockDocument('E'),
            $this->getMockDocument('F')
        ), true);
        $this->assertEquals(
            '<add overwrite="false"><doc><field name="id">E</field><field name="title">Item E</field></doc><doc><field name="id">F</field><field name="title">Item F</field></doc></add>',
            $sent
        );
    }
}
