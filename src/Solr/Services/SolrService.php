<?php

namespace SilverStripe\FullTextSearch\Solr\Services;

use SilverStripe\Core\Config\Config;
use SilverStripe\FullTextSearch\Solr\Solr;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use Silverstripe\Core\ClassInfo;

/**
 * The API for accessing the primary Solr installation, which includes both SolrService_Core,
 * plus extra methods for interrogating, creating, reloading and getting SolrService_Core instances
 * for Solr cores.
 */
class SolrService extends SolrService_Core
{
    private static $core_class = SolrService_Core::class;

    /**
     * Handle encoding the GET parameters and making the HTTP call to execute a core command
     */
    protected function coreCommand($command, $core, $params = array())
    {
        $command = strtoupper($command);
        $params = array_merge($params, array('action' => $command, 'wt' => 'json'));
        $params[$command == 'CREATE' ? 'name' : 'core'] = $core;

        return $this->_sendRawGet($this->_constructUrl('admin/cores', $params));
    }

    /**
     * Is the passed core active?
     * @param string $core The name of the core (an encoded class name)
     * @return boolean True if that core exists & is active
     */
    public function coreIsActive($core)
    {
        // Request the status of the full core name
        $result = $this->coreCommand('STATUS', $core);
        return isset($result->status->$core->uptime);
    }

    /**
     * Create a new core
     * @param $core string - The name of the core
     * @param $instancedir string - The base path of the core on the server
     * @param $config string - The filename of solrconfig.xml on the server. Default is $instancedir/solrconfig.xml
     * @param $schema string - The filename of schema.xml on the server. Default is $instancedir/schema.xml
     * @param $datadir string - The path to store data for this core on the server. Default depends on solrconfig.xml
     * @return Apache_Solr_Response
     */
    public function coreCreate($core, $instancedir, $config = null, $schema = null, $datadir = null)
    {
        $args = array('instanceDir' => $instancedir);
        if ($config) {
            $args['config'] = $config;
        }
        if ($schema) {
            $args['schema'] = $schema;
        }
        if ($datadir) {
            $args['dataDir'] = $datadir;
        }

        return $this->coreCommand('CREATE', $core, $args);
    }

    /**
     * Reload a core
     * @param $core string - The name of the core
     * @return Apache_Solr_Response
     */
    public function coreReload($core)
    {
        return $this->coreCommand('RELOAD', $core);
    }

    /**
     * Create a new Solr4Service_Core instance for the passed core
     * @param $core string - The name of the core
     * @return Solr4Service_Core
     */
    public function serviceForCore($core)
    {
        $klass = Config::inst()->get(get_called_class(), 'core_class');
        return new $klass($this->_host, $this->_port, $this->_path . $core, $this->_httpTransport);
    }
}
