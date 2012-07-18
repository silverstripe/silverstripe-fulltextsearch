# Solr connector for SilverStripe fulltextsearch module

## Introduction

This module provides a fulltextsearch module connector to Solr. 
It works with Solr in multi-core mode. It needs to be able to update Solr configuration files, and has modes for
doing this by direct file access (when Solr shares a server with SilverStripe) and by WebDAV (when it's on a different server).

See the helpful [Solr Tutorial](http://lucene.apache.org/fulltextsearch/api/doc-files/tutorial.html), for more on cores and querying.

## Requirements

Since Solr is Java based, it requires Java 1.5 or greater installed. 
It also requires a servlet container such as Tomcat, Jetty, or Resin.
Jetty is already packaged with the module.

See the official [Solr installation docs](http://wiki.apache.org/solr/SolrInstall)
for more information.

Note that these requirements are for the Solr server environment,
which doesn't have to be the same physical machine as the SilverStripe webhost.

## Installation

Configure Solr in file mode. The 'path' directory has to be writeable
by the user the Solr search server is started with (see below).

	// File: mysite/_config.php:
	<?php
	SearchUpdater::bind_manipulation_capture();
	Solr::configure_server(isset($solr_config) ? $solr_config : array(
		'host' => 'localhost',
		'indexstore' => array(
			'mode' => 'file',
			'path' => BASE_PATH . '/fulltextsearch/thirdparty/fulltextsearch/server/solr'
		)
	));

Create an index

	// File: mysite/code/MyIndex.php:
	<?php
	class MyIndex extends SolrIndex {
		function init() {
			$this->addClass('Page');
			$this->addAllFulltextFields();
		}
	}

Start the search server (via CLI, in a separate terminal window or background process)

	cd fulltextsearc/thirdparty/fulltextsearch/server/
	java -jar start.jar

Initialize the configuration (via CLI)

	sake dev/tasks/Solr_configure

Reindex

	sake dev/tasks/Solr_reindex

## Usage

TODO

## Debugging

You can visit `http://localhost:8983/solr/MyIndex/admin/` 
to search the contents of the now created Solr index via the native SOLR web interface.
Replace "MyIndex" with your own index definition as required.

It is possible to manually replicate the data automatically sent 
to Solr when saving/publishing in SilverStripe, 
which is useful when debugging front-end queries, 
see `thirdparty/fulltextsearch/server/silverstripe-solr-test.xml`.

	java -Durl=http://localhost:8983/solr/MyIndex/update/ -Dtype=text/xml -jar post.jar silverstripe-solr-test.xml

These instructions will get you running quickly, but the Solr indexes will be stored as binary files inside your SilverStripe project. You can also
copy the thirdparty/solr directory somewhere else. The instructions above will still apply - just set the path value
in mysite/_config.php to point to this other location, and of course run `java -jar start.jar` from the new directory,
not the thirdparty one.