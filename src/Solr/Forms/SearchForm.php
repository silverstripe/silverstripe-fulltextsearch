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
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

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
     * @return ArrayData
     */
    public function getResults()
    {
        // Get request data from request handler
        $request = $this->getRequestHandler()->getRequest();

        $searchTerms = $request->requestVar('Search');
        $query = SearchQuery::create()->addSearchTerm($searchTerms);

        if ($start = $request->requestVar('start')) {
            $query->setStart($start);
        }

        $params = [
            'spellcheck' => 'true',
            'spellcheck.collate' => 'true',
        ];

        // Get the first index
        $indexClasses = FullTextSearch::get_indexes(SolrIndex::class);
        $indexClass = reset($indexClasses);

        /** @var SolrIndex $index */
        $index = $indexClass::singleton();
        $results = $index->search($query, -1, -1, $params);

        // filter by permission
        if ($results) {
            foreach ($results->Matches as $match) {
                /** @var DataObject $match */
                if (!$match->canView()) {
                    $results->Matches->remove($match);
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
