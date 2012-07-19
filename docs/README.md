# WARNING: Heavily experimental API. Likely to change without notice.

# FullTextSearch module

An attempt to add stable support for Fulltext Search engines like Sphinx and Solr to SilverStripe CMS

## Maintainer Contact

* Hamish Friedlander <hamish (at) silverstripe (dot) com>

## Requirements

* SilverStripe 3.0

## Introduction

This is a module aimed at adding support for standalone fulltext search engines to SilverStripe.

It contains several layers:

* A fulltext API, ignoring the actual provision of fulltext searching

* A connector API, providing common code to allow connecting a fulltext searching engine to the fulltext API, and

* Some connectors for common fulltext searching engines.

## Reasoning

There are several fulltext search engines that work in a similar manner. They build indexes of denormalized data that
is then searched through using some custom query syntax.

Traditionally, fulltext search connectors for SilverStripe have attempted to hide this design, instead presenting
fulltext searching as an extension of the object model. However the disconnect between the fulltext search engine's
design and the object model meant that searching was inefficient. The abstraction would also often break and it was
hard to then figure out what was going on.

This module instead provides the ability to define those indexes and queries in PHP. The indexes are defined as a mapping
between the SilverStripe object model and the connector-specific fulltext engine index model. This module then interrogates model metadata 
to build the specific index definition. 

It also hooks into Sapphire in order to update the indexes when the models change and connectors then convert those index and query definitions 
into fulltext engine specific code.

The intent of this module is not to make changing fulltext search engines seamless. Where possible this module provides
common interfaces to fulltext engine functionality, abstracting out common behaviour. However, each connector also
offers its own extensions, and there is some behaviour (such as getting the fulltext search engines installed, configured
and running) that each connector deals with itself, in a way best suited to that search engine's design.

## Basic usage

Basic usage is a four step process:

1). Define an index in SilverStripe (Note: The specific connector index instance - that's what defines which engine gets used)

```php
mysite/code/MyIndex.php:

<?php

class MyIndex extends SolrIndex {
	function init() {
		$this->addClass('Page');
		$this->addFulltextField('DocumentContents');
	}
}
```

2). Add something to the index (Note: You can also just update an existing document in the CMS. but adding _existing_ objects to the index is connector specific)

```php
$page = new Page(array('Contents' => 'Help me. My house is on fire. This is less than optimal.'));
$page->write();
```

3). Build a query

```php
$query = new SearchQuery();
$query->search('My house is on fire');
```

4). Apply that query to an index

```php
$results = singleton($index)->search($query);
```

Note that for most connectors, changes won't be searchable until _after_ the request that triggered the change.

## Connectors

### Solr

See Solr.md

### Sphinx

Not written yet
