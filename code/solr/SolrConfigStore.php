<?php

/**
 * Class SolrConfigStore
 *
 * The interface Solr_Configure uses to upload configuration files to Solr
 */
interface SolrConfigStore
{
    /**
     * Upload a file to Solr for index $index
     * @param $index string - The name of an index (which is also used as the name of the Solr core for the index)
     * @param $file string - A path to a file to upload. The base name of the file will be used on the remote side
     * @return null
     */
    public function uploadFile($index, $file);

    /**
     * Upload a file to Solr from a string for index $index
     * @param $index string - The name of an index (which is also used as the name of the Solr core for the index)
     * @param $filename string - The base name of the file to use on the remote side
     * @param $strong string - The contents of the file
     * @return null
     */
    public function uploadString($index, $filename, $string);

    /**
     * Get the instanceDir to tell Solr to use for index $index
     * @param $index string - The name of an index (which is also used as the name of the Solr core for the index)
     */
    public function instanceDir($index);
}

/**
 * Class SolrConfigStore_File
 *
 * A ConfigStore that uploads files to a Solr instance on a locally accessible filesystem
 * by just using file copies
 */
class SolrConfigStore_File implements SolrConfigStore
{
    public function __construct($config)
    {
        $this->local = $config['path'];
        $this->remote = isset($config['remotepath']) ? $config['remotepath'] : $config['path'];
    }

    public function getTargetDir($index)
    {
        $targetDir = "{$this->local}/{$index}/conf";

        if (!is_dir($targetDir)) {
            $worked = @mkdir($targetDir, 0770, true);

            if (!$worked) {
                throw new RuntimeException(
                    sprintf('Failed creating target directory %s, please check permissions', $targetDir)
                );
            }
        }

        return $targetDir;
    }

    public function uploadFile($index, $file)
    {
        $targetDir = $this->getTargetDir($index);
        copy($file, $targetDir.'/'.basename($file));
    }

    public function uploadString($index, $filename, $string)
    {
        $targetDir = $this->getTargetDir($index);
        file_put_contents("$targetDir/$filename", $string);
    }

    public function instanceDir($index)
    {
        return $this->remote.'/'.$index;
    }
}

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
            isset($config['auth']) ? $config['auth'].'@' : '',
            $options['host'].':'.(isset($config['port']) ? $config['port'] : $options['port']),
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
        WebDAV::upload_from_file($file, $targetDir.'/'.basename($file));
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
