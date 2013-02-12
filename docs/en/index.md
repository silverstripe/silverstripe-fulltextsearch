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

It also hooks into SilverStripe framework in order to update the indexes when the models change and connectors then convert those index and query definitions 
into fulltext engine specific code.

The intent of this module is not to make changing fulltext search engines seamless. Where possible this module provides
common interfaces to fulltext engine functionality, abstracting out common behaviour. However, each connector also
offers its own extensions, and there is some behaviour (such as getting the fulltext search engines installed, configured
and running) that each connector deals with itself, in a way best suited to that search engine's design.

## Basic usage

Basic usage is a four step process:

1). Define an index in SilverStripe (Note: The specific connector index instance - that's what defines which engine gets used)

	// File: mysite/code/MyIndex.php:
	<?php
	class MyIndex extends SolrIndex {
		function init() {
			$this->addClass('Page');
			$this->addFulltextField('Title');
			$this->addFulltextField('Content');
		}
	}

You can also skip listing all searchable fields, and have the index
figure it out automatically via `addAllFulltextFields()`.

2). Add something to the index (Note: You can also just update an existing document in the CMS. but adding _existing_ objects to the index is connector specific)

	$page = new Page(array('Content' => 'Help me. My house is on fire. This is less than optimal.'));
	$page->write();

Note: There's usually a connector-specific "reindex" task for this.

3). Build a query

	$query = new SearchQuery();
	$query->search('My house is on fire');

4). Apply that query to an index

	$results = singleton('MyIndex')->search($query);

Note that for most connectors, changes won't be searchable until _after_ the request that triggered the change.

## Searching Specific Fields

By default, the index searches through all indexed fields.
This can be limited by arguments to the `search()` call.

	$query = new SearchQuery();
	$query->search('My house is on fire', array('Page_Title'));
	// No results, since we're searching in title rather than page content
	$results = singleton('MyIndex')->search($query);

## Searching Value Ranges

Most values can be expressed as ranges, most commonly dates or numbers.
To search for a range of values rather than an exact match, 
use the `SearchQuery_Range` class. The range can include bounds on both sides,
or stay open ended by simply leaving the argument blank.

	$query = new SearchQuery();
	$query->search('My house is on fire');
	// Only include documents edited in 2011 or earlier
	$query->filter('Page_LastEdited', new SearchQuery_Range(null, '2011-12-31T23:59:59Z'));
	$results = singleton('MyIndex')->search($query);	

Note: At the moment, the date format is specific to the search implementation.

## Searching Empty or Existing Values

Since there's a type conversion between the SilverStripe database, object properties
and the search index persistence, its often not clear which condition is searched for.
Should it equal an empty string, or only match if the field wasn't indexed at all?
The `SearchQuery` API has the concept of a "missing" and "present" field value for this:

	$query = new SearchQuery();
	$query->search('My house is on fire');
	// Needs a value, although it can be false
	$query->filter('Page_ShowInMenus', SearchQuery::$present);
	$results = singleton('MyIndex')->search($query);	

## Indexing Multiple Classes

An index is a denormalized view of your data, so can hold data from more than one model.
As you can only search one index at a time, all searchable classes need to be included.

	// File: mysite/code/MyIndex.php:
	<?php
	class MyIndex extends SolrIndex {
		function init() {
			$this->addClass('Page');
			$this->addClass('Member');
			$this->addFulltextField('Content'); // only applies to Page class
			$this->addFulltextField('FirstName'); // only applies to Member class
		}
	}

## Indexing Relationships

TODO

## Weighting/Boosting Fields

Results aren't all created equal. Matches in some fields are more important
than others, for example terms in a page title rather than its content
might be considered more relevant to the user.

To account for this, a "weighting" (or "boosting") factor can be applied to each
searched field. The default is 1.0, anything below that will decrease the relevance,
anthing above increases it.

Example:

	$query = new SearchQuery();
	$query->search(
		'My house is on fire', 
		null,
		array(
			'Page_Title' => 1.5,
			'Page_Content' => 1.0
		)
	);
	$results = singleton('MyIndex')->search($query);

## Filtering

## Connectors

### Solr

See Solr.md

### Sphinx

Not written yet

## FAQ

### How do I exclude draft pages from the index?

By default, the `SearchUpdater` class indexes all available "variant states",
so in the case of the `Versioned` extension, both "draft" and "live".
For most cases, you'll want to exclude draft content from your search results.

You can either prevent the draft content from being indexed in the first place,
by adding the following to your `SearchIndex->init()` method:

	$this->excludeVariantState(array('SearchVariantVersioned' => 'Stage'));

Alternatively, you can index draft content, but simply exclude it from searches. 
This can be handy to preview search results on unpublished content, in case a CMS author is logged in.
Before constructing your `SearchQuery`, conditionally switch to the "live" stage:

	if(!Permission::check('CMS_ACCESS_CMSMain')) Versioned::reading_stage('Live');
	$query = new SearchQuery();
	// ...

### How do I write nested/complex filters?

TODO
