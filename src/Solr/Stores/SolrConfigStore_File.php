<?php

namespace SilverStripe\FullTextSearch\Solr\Stores;

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
                throw new \RuntimeException(
                    sprintf('Failed creating target directory %s, please check permissions', $targetDir)
                );
            }
        }

        return $targetDir;
    }

    public function uploadFile($index, $file)
    {
        $targetDir = $this->getTargetDir($index);
        copy($file, $targetDir . '/' . basename($file));
    }

    public function uploadString($index, $filename, $string)
    {
        $targetDir = $this->getTargetDir($index);
        file_put_contents("$targetDir/$filename", $string);
    }

    public function instanceDir($index)
    {
        return $this->remote . '/' . $index;
    }
}
