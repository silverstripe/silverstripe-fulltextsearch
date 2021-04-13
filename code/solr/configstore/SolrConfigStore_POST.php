<?php
/**
 * Class SolrConfigStore_POST
 *
 * A ConfigStore that uploads files to a Solr instance via a WebDAV server
 */
class SolrConfigStore_POST implements SolrConfigStore
{
    protected $url = '';

    protected $remote = '';

    public function __construct($config)
    {
        $options = Solr::solr_options();

        $this->url = implode('', array(
            'http://',
            isset($config['auth']) ? $config['auth'].'@' : '',
            $options['host'].':'.(isset($config['port']) ? $config['port'] : $options['port']),
            $config['path']
        ));

        if (isset($config['remotepath'])) {
            $this->remote = $config['remotepath'];
        }
    }

    public function uploadFile($index, $file)
    {
        $this->uploadString($index, basename($file), file_get_contents($file));
    }

    public function uploadString($index, $filename, $string)
    {
        $targetDir = "{$this->url}/config/$index";

        file_get_contents($targetDir . '/' . $filename, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/octet-stream',
                'content' => (string) $string
            ]
        ]));
    }

    public function instanceDir($index)
    {
        return $this->remote ? "{$this->remote}/$index" : $index;
    }
}
