<?php

namespace SilverStripe\FullTextSearch\Solr\Stores;

use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Utils\WebDAV;

/**
 * Class SolrConfigStore_WebDAV
 *
 * A ConfigStore that uploads files to a Solr instance via a WebDAV server
 */
class SolrConfigStore_WebDAV implements SolrConfigStore
{
    public function __construct($config)
    {
        $options = Solr::solr_options();

        $this->url = implode('', array(
            'http://',
            isset($config['auth']) ? $config['auth'] . '@' : '',
            $options['host'] . ':' . (isset($config['port']) ? $config['port'] : $options['port']),
            $config['path']
        ));
        $this->remote = $config['remotepath'];
    }

    public function getTargetDir($index)
    {
        $indexdir = "{$this->url}/$index";
        if (!WebDAV::exists($indexdir)) {
            WebDAV::mkdir($indexdir);
        }

        $targetDir = "{$this->url}/$index/conf";
        if (!WebDAV::exists($targetDir)) {
            WebDAV::mkdir($targetDir);
        }

        return $targetDir;
    }

    public function uploadFile($index, $file)
    {
        $targetDir = $this->getTargetDir($index);
        WebDAV::upload_from_file($file, $targetDir . '/' . basename($file));
    }

    public function uploadString($index, $filename, $string)
    {
        $targetDir = $this->getTargetDir($index);
        WebDAV::upload_from_string($string, "$targetDir/$filename");
    }

    public function instanceDir($index)
    {
        return $this->remote ? "{$this->remote}/$index" : $index;
    }
}
