<?php

namespace SilverStripe\FullTextSearch\Solr\Control;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\FullTextSearch\Solr\Forms\SearchForm;
use SilverStripe\ORM\FieldType\DBField;

class ContentControllerExtension extends Extension
{
    private static $allowed_actions = array(
        'SearchForm',
        'results',
    );

    /**
     * Site search form
     *
     * @return SearchForm
     */
    public function SearchForm()
    {
        $searchText =  _t('SilverStripe\\CMS\\Search\\SearchForm.SEARCH', 'Search');
        /** @var HTTPRequest $currentRequest */
        $currentRequest = $this->owner->getRequest();

        if ($currentRequest && $currentRequest->getVar('Search')) {
            $searchText = $currentRequest->getVar('Search');
        }

        $fields = FieldList::create(
            TextField::create('Search', false, $searchText)
        );
        $actions = FieldList::create(
            FormAction::create('results', _t('SilverStripe\\CMS\\Search\\SearchForm.GO', 'Go'))
        );
        return SearchForm::create($this->owner, 'SearchForm', $fields, $actions);
    }

    /**
     * Process and render search results.
     *
     * @param array $data The raw request data submitted by user
     * @param SearchForm $form The form instance that was submitted
     * @param HTTPRequest $request Request generated for this action
     */
    public function results($data, $form, $request)
    {
        $data = [
            'Results' => $form->getResults(),
            'Query' => DBField::create_field('Text', $form->getSearchQuery()),
            'Title' => _t('SilverStripe\\CMS\\Search\\SearchForm.SearchResults', 'Search Results')
        ];
        return $this->owner->customise($data)->renderWith(['Page_results_solr', 'Page_results', 'Page']);
    }
}
