# Solr connector for SilverStripe fulltextsearch

This module provides a fulltextsearch module connector to Solr.

It works with Solr in multi-core mode. It needs to be able to update Solr configuration files, and has modes for
doing this by direct file access (when Solr shares a server with SilverStripe) and by WebDAV (when it's on a different server).

Since Solr is Java based, this module requires a Java runtime to be present on the server Solr is running on (not nessecarily
the same server the SilverStripe server is on).

## Getting started quickly (dev mode)

Configure Solr in file mode

```php
mysite/_config.php:

<?php

Solr::configure_server(isset($solr_config) ? $solr_config : array(
	'host' => 'localhost',
	'indexstore' => array(
		'mode' => 'file',
		'path' => BASE_PATH . '/fulltextsearch/thirdparty/solr/server/solr'
	)
));
```

Create an index

```php
mysite/code/MyIndex.php:

<?php

class MyIndex extends SolrIndex {
	function init() {
		$this->addClass('Page');
		$this->addAllFulltextFields();
	}
}
```

Open a terminal, change to thirdparty/solr/server and start Solr by running `java -jar start.jar`

In another terminal run the configure task `sake dev/tasks/Solr_configure`

Tne run the configure task `sake dev/tasks/Solr_reindex`

You can now visit http://localhost:8983/solr/MyIndex/admin/ to search the contents of the now created Solr index

-----

These instructions will get you running quickly, but the Solr indexes will be stored in your project. You can also
copy the thirdparty/solr directory somewhere else. The instructions are above still apply then - just set the path value
in mysite/_config.php to point to this other location, and of course run `java -jar start.jar` from the new directory
not the thirdparty one.

