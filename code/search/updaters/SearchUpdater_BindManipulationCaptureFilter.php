<?php

namespace SilverStripe\FullTextSearch\Search\Updaters;

use SilverStripe\Control\RequestFilter;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\ORM\DataModel;
use SilverStripe\Control\HTTPResponse;

class SearchUpdater_BindManipulationCaptureFilter implements RequestFilter
{

    public function preRequest(HTTPRequest $request, Session $session, DataModel $model)
    {
        SearchUpdater::bind_manipulation_capture();
    }

    public function postRequest(HTTPRequest $request, HTTPResponse $response, DataModel $model)
    {
        /* NOP */
    }
}