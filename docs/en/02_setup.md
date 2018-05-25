# Setup

The fulltextsearch module includes support for connecting to Solr.

It works with Solr in multi-core mode. It needs to be able to update Solr configuration files, and has modes for doing this by direct file access (when Solr shares a server with SilverStripe) and by WebDAV (when it's on a different server).

See the helpful [Solr Tutorial](http://lucene.apache.org/solr/4_5_1/tutorial.html), for more on cores
and querying.

## Requirements

Since Solr is Java based, it requires Java 1.5 or greater installed.

When you're installing it yourself, it also requires a servlet container such as Tomcat, Jetty, or Resin. For
development testing there is a standalone version that comes bundled with Jetty (see [Installing Solr](#installing-solr) below).

See the official [Solr installation docs](http://wiki.apache.org/solr/SolrInstall) for more information.

Note that these requirements are for the Solr server environment, which doesn't have to be the same physical machine as the SilverStripe webhost.

## Installing Solr

### Local installation

If you'll be running Solr on the same machine as your SilverStripe installation, you can use the [silverstripe/fulltextsearch-localsolr module](https://github.com/silverstripe-archive/silverstripe-fulltextsearch-localsolr). This can also be useful as a development dependency. You can bring it in via composer (use `require-dev` if you plan to use install Solr remotely in Production):

```bash
composer require silverstripe/fulltextsearch-localsolr
```

Once installed, start the server via CLI:

```bash
cd fulltextsearch-localsolr/server
java -jar start.jar
```

Then configure Solr to use `file` more with the following configuration in your `app/_config.php`, making sure that the `path` directory is writeable by the user that started the server (above):

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



## Installing the module



## Solr admin
