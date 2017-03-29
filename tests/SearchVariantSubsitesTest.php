<?php

class SearchVariantSubsiteTest extends SapphireTest
{

    private static $index = null;


    public function setUp()
    {
        parent::setUp();

        // Check versioned available
        if (!class_exists('Subsite')) {
            return $this->markTestSkipped('The versioned decorator is not installed');
        }

        if (self::$index === null) {
            self::$index = singleton('SearchVariantSubsiteTest');
        }

        SearchUpdater::bind_manipulation_capture();

        Config::inst()->update('Injector', 'SearchUpdateProcessor', array(
            'class' => 'SearchUpdateImmediateProcessor'
        ));

        FullTextSearch::force_index_list(self::$index);
        SearchUpdater::clear_dirty_indexes();
    }

    public function testQueryIsAlteredWhenSubsiteNotSet()
    {
        $index = new SolrIndexTest_FakeIndex();
        $query = new SearchQuery();

        //typical behaviour: nobody is explicitly filtering on subsite, so the search variant adds a filter to the query
        $this->assertTrue(empty($query->require['_subsite']));
        $variant = new SearchVariantSubsites();
        $variant->alterDefinition('SearchUpdaterTest_Container', $index);
        $variant->alterQuery($query, $index);

        //check that the "default" query has been put in place: it's not empty, and we're searching on Subsite ID:0 and
        // an object of SearchQuery::missing
        $this->assertFalse(empty($query->require['_subsite']));
        $this->assertTrue($query->require['_subsite'][0] == 0);
        $this->assertTrue($query->require['_subsite'][1] instanceof SearchQuery::$missing);
    }


    public function testQueryIsAlteredWhenSubsiteIsSet()
    {
        //now we want to test if somebody has already applied the _subsite filter to the query
        $index = new SolrIndexTest_FakeIndex();
        $query = new SearchQuery();

        //check that _subsite is not applied yet
        $this->assertTrue(empty($query->require['_subsite']));

        //apply the subsite filter on the query (for example, if it's passed into a controller and set before searching)
        //we've chosen an arbirary value of 2 here, to check if it is changed later
        $query->filter('_subsite', 2);
        $this->assertFalse(empty($query->require['_subsite']));

        //apply the search variant's definition and query
        $variant = new SearchVariantSubsites();
        $variant->alterDefinition('SearchUpdaterTest_Container', $index);

        //the protected function isFieldFiltered is implicitly tested here
        $variant->alterQuery($query, $index);

        //confirm that the query has been altered, but NOT with default values
        //first check that _subsite filter is not empty
        $this->assertFalse(empty($query->require['_subsite']));
        //subsite filter first value is not 0
        $this->assertFalse($query->require['_subsite'][0] == 0);
        //subsite filter second value is not SearchQuery::missing
        $this->assertFalse($query->require['_subsite'][1] instanceof SearchQuery::$missing);

        //subsite filter has been modified with our arbitrary test value. The second value is not set
        //this proves that the query has not been altered by the variant
        $this->assertTrue($query->require['_subsite'][0] == 2);
        $this->assertTrue(empty($query->require['_subsite'][1]));
    }

}

