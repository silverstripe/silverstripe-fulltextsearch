<?php

namespace SilverStripe\FullTextSearch\Solr\Forms;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;

class SearchForm extends Form
{
    private static $casting = array(
        'SearchQuery' => 'Text'
    );

    /**
     * @param RequestHandler $controller
     * @param string $name The name of the form (used in URL addressing)
     * @param FieldList $fields Optional, defaults to a single field named "Search". Search logic needs to be customized
     *  if fields are added to the form.
     * @param FieldList $actions Optional, defaults to a single field named "Go".
     */
    public function __construct(
        RequestHandler $controller = null,
        $name = 'SearchForm',
        FieldList $fields = null,
        FieldList $actions = null
    ) {
        if (!$fields) {
            $fields = FieldList::create(
                TextField::create('Search', _t(__CLASS__.'.SEARCH', 'Search'))
            );
        }

        if (!$actions) {
            $actions = FieldList::create(
                FormAction::create("results", _t(__CLASS__.'.GO', 'Go'))
            );
        }

        parent::__construct($controller, $name, $fields, $actions);

        $this->setFormMethod('get');

        $this->disableSecurityToken();
    }

    /**
     * Return dataObjectSet of the results using current request to get info from form.
     * Simplest implementation loops over all Solr indexes
     *
     * @return ArrayList
     */
    public function getResults()
    {
        // Get request data from request handler
        $request = $this->getRequestHandler()->getRequest();

        $searchTerms = $request->requestVar('Search');
        $query = SearchQuery::create()->addSearchTerm($searchTerms);

        $indexes = FullTextSearch::get_indexes(SolrIndex::class);
        $results = ArrayList::create();

        /** @var SolrIndex $index */
        foreach ($indexes as $index) {
            $results->merge($index->search($query)->Matches);
        }

        // filter by permission
        if ($results) {
            /** @var DataObject $result */
            foreach ($results as $result) {
                if (!$result->canView()) {
                    $results->remove($result);
                }
            }
        }

        return $results;
    }

    /**
     * Get the search query for display in a "You searched for ..." sentence.
     *
     * @return string
     */
    public function getSearchQuery()
    {
        return $this->getRequestHandler()->getRequest()->requestVar('Search');
    }
}
