# Setup

The FulltextSearch module includes support for connecting to Solr.

It works with Solr in multi-core mode. It needs to be able to update Solr configuration files, and has modes for doing 
so by direct file access (when Solr shares a server with SilverStripe) and by WebDAV (when it's on a different server).

See the helpful [Solr Tutorial](http://lucene.apache.org/solr/4_5_1/tutorial.html), for more on cores and querying.

## Requirements

Since Solr is Java based, it requires Java 1.5 or greater installed.

When you're installing it yourself, it also requires a servlet container such as Tomcat, Jetty, or Resin. For
development testing there is a standalone version that comes bundled with Jetty (see [Installing Solr](#installing-solr)
 below).

See the official [Solr installation docs](http://wiki.apache.org/solr/SolrInstall) for more information.

Note that these requirements are for the Solr server environment, which doesn't have to be the same physical machine as 
the SilverStripe webhost.

## Installing Solr

### Local installation

If you'll be running Solr on the same machine as your SilverStripe installation, and the 
[quick start script](01_getting_started.md#quick-start) doesn't suit your needs, you can use the 
[fulltextsearch-localsolr module](https://github.com/silverstripe-archive/silverstripe-fulltextsearch-localsolr). This 
can also be useful as a development dependency. You can bring it in via composer (use `require-dev` if you plan to 
install Solr remotely in Production):

```bash
composer require silverstripe/fulltextsearch-localsolr
```

Once installed, start the server via CLI:

```bash
cd fulltextsearch-localsolr/server
java -jar start.jar
```

Then configure the module to use `file` mode with the following configuration in your `app/_config.php`, making sure 
that the `path` directory is writeable by the user that started the server (above):

```php
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'host' => 'localhost',
    'indexstore' => [
        'mode' => 'file',
        'path' => BASE_PATH . '/.solr'
    ]
]);
```

### Remote installation

Alternatively, it can be beneficial to keep the Solr service contained on its own infrastructure, for performance and
security reasons. The [Common Web Platform (CWP)](www.cwp.govt.nz) uses Solr in this manner. To do so, you should 
install the dependencies on the remote server, and then configure the module to use the `webdav` mode like so:

```php
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'host' => 'remotesolrserver.com', // IP address or hostname
    'indexstore' => [
        'mode' => 'webdav',
        'path' => BASE_PATH . '/webdav',
    ]
]);
```

Check all the available [configuration options](03_configuration.md#solr-server-parameters) to fine-tune the module to 
work with your desired setup.

This will mean that all configuration files, and the indexes themselves, are stored remotely.

## Solr admin

Solr provides an administration interface with a GUI to allow you to get at the finer details of your cores and 
configuration. You can access it at example.com:<SOLR_PORT>/<SOLR_PATH>/#/ on a local installation 
(usually example.com:8983/solr/#/).

There you can access logging, run raw queries against your stored indexes, and get some basic performance metrics. 
Additionally, you can perform more drastic changes, such as dropping and reloading cores.

For a comprehensive look at the Solr admin interface, read the
[user guide for Solr 4.10](http://archive.apache.org/dist/lucene/solr/ref-guide/apache-solr-ref-guide-4.10.pdf#page=17)
