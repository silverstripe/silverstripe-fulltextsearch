<?php
namespace SilverStripe\FullTextSearch\Solr\Stores;

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
