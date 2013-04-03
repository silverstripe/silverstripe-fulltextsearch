# FullTextSearch module

[![Build Status](https://secure.travis-ci.org/silverstripe-labs/silverstripe-fulltextsearch.png?branch=master)](http://travis-ci.org/silverstripe-labs/silverstripe-fulltextsearch)

Adds support for fulltext search engines like Sphinx and Solr to SilverStripe CMS.

## Maintainer Contact

* Hamish Friedlander <hamish (at) silverstripe (dot) com>

## Requirements

* SilverStripe 3.1+
* (optional) [silverstripe-phockito](https://github.com/hafriedlander/silverstripe-phockito) (for testing)

## Documentation

See docs/en/index.md

## TODO

* Get rid of includeSubclasses - isn't actually used in practice, makes the codebase uglier, and ClassHierarchy can be
used at query time for most of the same use cases

* Fix field referencing in queries. Should be able to do `$query->search('Text', 'Content')`, not
`$query->search('Text', 'SiteTree_Content')` like you have to do now

    - Make sure that when field exists in multiple classes, searching against bare fields searches all of them

    - Allow searching against specific instances too

* Make fields restrictable by class in an index - 'SiteTree#Content' to limit fields to a particular class,
maybe 'Content->Summary' to allow calling a specific method on the field object to get the text

* Allow following user relationships (Children.Foo for example)

* Be clearer about what happens with relationships to stateful objects (e.g. Parent.Foo where Parent is versioned)

* Improvements to SearchUpdater

     - Make it work properly when in-between objects (the A in A.B.Foo) update

     - Allow user logic to cause triggering reindex of documents when field is user generated

* Add sphinx connector

* Add generic APIs for spell correction, file text extraction and snippet generation

* Better docs
