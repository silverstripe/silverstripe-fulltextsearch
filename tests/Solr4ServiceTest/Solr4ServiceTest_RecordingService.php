<?php

namespace SilverStripe\FullTextSearch\Tests\Solr4ServiceTest;

use SilverStripe\FullTextSearch\Solr\Services\Solr4Service_Core;

class Solr4ServiceTest_RecordingService extends Solr4Service_Core
{
    protected function _sendRawPost($url, $rawPost, $timeout = false, $contentType = 'text/xml; charset=UTF-8')
    {
        return $rawPost;
    }
    
    protected function _sendRawGet($url, $timeout = false)
    {
        return $url;
    }
}
