<?php


class SearchUpdater_BindManipulationCaptureFilter implements RequestFilter
{
    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model)
    {
        SearchUpdater::bind_manipulation_capture();
    }

    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        /* NOP */
    }
}
