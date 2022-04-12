<?php

namespace SilverStripe\FullTextSearch\Tests;

use \InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\FullTextSearch\Search\Adapters\SolrSearchAdapter;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriteria;
use SilverStripe\FullTextSearch\Search\Criteria\SearchCriterion;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterBasic;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterIn;
use SilverStripe\FullTextSearch\Solr\Writers\SolrSearchQueryWriterRange;
use SilverStripe\FullTextSearch\Tests\SolrIndexTest\SolrIndexTest_FakeIndex;

/**
 * Class SolrWritersTest
 * @package SilverStripe\FullTextSearch\Tests
 */
class SolrWritersTest extends SapphireTest
{
    public function testBasicEqualQueryString()
    {
        $criteria = new SearchCriterion('Title', 'Test', SearchCriterion::EQUAL);
        $writer = SolrSearchQueryWriterBasic::create();
        $expected = '+(Title:"Test")';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicNotEqualQueryString()
    {
        $criteria = new SearchCriterion('Title', 'Test', SearchCriterion::NOT_EQUAL);
        $writer = SolrSearchQueryWriterBasic::create();
        $expected = '-(Title:"Test")';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicInQueryString()
    {
        $criteria = new SearchCriterion('ID', [1,2,3], SearchCriterion::IN);
        $writer = SolrSearchQueryWriterIn::create();
        $expected = '+(ID:1 ID:2 ID:3)';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicNotInQueryString()
    {
        $criteria = new SearchCriterion('ID', [1,2,3], SearchCriterion::NOT_IN);
        $writer = SolrSearchQueryWriterIn::create();
        $expected = '-(ID:1 ID:2 ID:3)';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicGreaterEqualQueryString()
    {
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::GREATER_EQUAL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[2 TO *])';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicGreaterQueryString()
    {
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::GREATER_THAN);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:{2 TO *})';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicLessEqualQueryString()
    {
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::LESS_EQUAL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[* TO 2])';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicLessQueryString()
    {
        $criteria = new SearchCriterion('Stock', 2, SearchCriterion::LESS_THAN);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:{* TO 2})';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicIsNullQueryString()
    {
        $criteria = new SearchCriterion('Stock', null, SearchCriterion::ISNULL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '-(Stock:[* TO *])';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testBasicIsNotNullQueryString()
    {
        $criteria = new SearchCriterion('Stock', null, SearchCriterion::ISNOTNULL);
        $writer = SolrSearchQueryWriterRange::create();
        $expected = '+(Stock:[* TO *])';

        $this->assertEquals($expected, $writer->generateQueryString($criteria));
    }

    public function testConjunction()
    {
        $adapter = new SolrSearchAdapter();

        $this->assertEquals(' AND ', $adapter->getConjunctionFor(SearchCriteria::CONJUNCTION_AND));
        $this->assertEquals(' OR ', $adapter->getConjunctionFor(SearchCriteria::CONJUNCTION_OR));
    }

    public function testConjunctionFailure()
    {
        $this->expectException(\InvalidArgumentException::class);
        $adapter = new SolrSearchAdapter();
        $adapter->getConjunctionFor('FAIL');
    }

    /**
     * @throws \Exception
     */
    public function testComplexPositiveFilterQueryString()
    {
        $expected = '+((+(Page_TaxonomyTerms_ID:"Lego") AND +(Page_TaxonomyTerms_ID:"StarWars") AND +(Stock:[5 TO *]))';
        $expected .= ' OR (+(Page_TaxonomyTerms_ID:"Books") AND +(Page_TaxonomyTerms_ID:"HarryPotter")';
        $expected .= ' AND +(Stock:[1 TO *])))';

        $legoCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Lego',
            ],
            SearchCriterion::IN
        );

        $legoCriteria->addAnd(
            'Page_TaxonomyTerms_ID',
            [
                'StarWars',
            ],
            SearchCriterion::IN
        );

        $legoCriteria->addAnd(
            'Stock',
            5,
            SearchCriterion::GREATER_EQUAL
        );

        $booksCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Books',
            ],
            SearchCriterion::IN
        );

        $booksCriteria->addAnd(
            'Page_TaxonomyTerms_ID',
            [
                'HarryPotter',
            ],
            SearchCriterion::IN
        );

        $booksCriteria->addAnd(
            'Stock',
            1,
            SearchCriterion::GREATER_EQUAL
        );

        // Combine the two criteria with an `OR` conjunction
        $criteria = SearchCriteria::create($legoCriteria)->addOr($booksCriteria);

        $query = SearchQuery::create();
        $query->filterBy($criteria);

        $index = new SolrIndexTest_FakeIndex();

        $this->assertTrue(in_array($expected, $index->getFiltersComponent($query) ?? []));
    }

    /**
     * @throws \Exception
     */
    public function testComplexNegativeFilterQueryString()
    {
        $expected = '+((-(Page_TaxonomyTerms_ID:"Lego" Page_TaxonomyTerms_ID:"StarWars") AND +(Stock:[* TO 5]))';
        $expected .= ' OR (-(Page_TaxonomyTerms_ID:"Books" Page_TaxonomyTerms_ID:"HarryPotter")';
        $expected .= ' AND +(Stock:[* TO 2])))';

        $legoCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Lego',
                'StarWars',
            ],
            SearchCriterion::NOT_IN
        );

        $legoCriteria->addAnd(
            'Stock',
            5,
            SearchCriterion::LESS_EQUAL
        );

        $booksCriteria = SearchCriteria::create(
            'Page_TaxonomyTerms_ID',
            [
                'Books',
                'HarryPotter',
            ],
            SearchCriterion::NOT_IN
        );

        $booksCriteria->addAnd(
            'Stock',
            2,
            SearchCriterion::LESS_EQUAL
        );

        // Combine the two criteria with an `OR` conjunction
        $criteria = SearchCriteria::create($legoCriteria)->addOr($booksCriteria);

        $query = SearchQuery::create();
        $query->filterBy($criteria);

        $index = new SolrIndexTest_FakeIndex();

        $this->assertTrue(in_array($expected, $index->getFiltersComponent($query) ?? []));
    }
}
