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

	cd fulltextsearch/thirdparty/fulltextsearch/server/
	java -jar start.jar

Initialize the configuration (via CLI)

	sake dev/tasks/Solr_configure

## Usage

After configuring Solr, you have the option to add your existing
content to its indices. Run the following command:

	sake dev/tasks/Solr_reindex

This will rebuild all indices. You can narrow down the operation with the following options:

 - `index`: PHP class name of an index
 - `class`: PHP model class to reindex
 - `start`: Offset (applies to matched records)
 - `variantstate`: JSON encoded string with state, e.g. '{"SearchVariantVersioned":"Stage"}'
 - `verbose`: Debug information

Note: The Solr indexes will be stored as binary files inside your SilverStripe project. 
You can also copy the `thirdparty/`solr directory somewhere else,
just set the path value in `mysite/_config.php` to point to the new location.
And of course run `java -jar start.jar` from the new directory.

## Debugging

### Using the web admin interface

You can visit `http://localhost:8983/solr`, which will show you a list
to the admin interfaces of all available indices.
There you can search the contents of the index via the native SOLR web interface.

It is possible to manually replicate the data automatically sent 
to Solr when saving/publishing in SilverStripe, 
which is useful when debugging front-end queries, 
see `thirdparty/fulltextsearch/server/silverstripe-solr-test.xml`.

	java -Durl=http://localhost:8983/solr/MyIndex/update/ -Dtype=text/xml -jar post.jar silverstripe-solr-test.xml

## FAQ

### How do I use date ranges where dates might not be defined?

The Solr index updater only includes dates with values,
so the field might not exist in all your index entries.
A simple bounded range query (`<field>:[* TO <date>]`) will fail in this case.
In order to query the field, reverse the search conditions and exclude the ranges you don't want:

	// Wrong: Filter will ignore all empty field values
	$myQuery->filter(<field>, new SearchQuery_Range('*', <date>));
	// Better: Exclude the opposite range
	$myQuery->exclude(<field>, new SearchQuery_Range(<date>, '*'));