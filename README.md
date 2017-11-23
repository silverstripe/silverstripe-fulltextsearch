# FullTextSearch module

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-fulltextsearch.svg?style=flat)](https://travis-ci.org/silverstripe/silverstripe-fulltextsearch)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-fulltextsearch/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-fulltextsearch/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-fulltextsearch/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-fulltextsearch)

Adds support for fulltext search engines like Sphinx and Solr to SilverStripe CMS.

## Maintainer Contact

* Hamish Friedlander <hamish (at) silverstripe (dot) com>

## Requirements

* SilverStripe 4.0+
* (optional) [silverstripe-phockito](https://github.com/hafriedlander/silverstripe-phockito) (for testing)

**Note:** For SilverStripe 3.x, please use the [2.x release line](https://github.com/silverstripe/silverstripe-fulltextsearch/tree/2).

## Documentation

See docs/en/index.md

For details of updates, bugfixes, and features, please see the [changelog](CHANGELOG.md).

## TODO

* Get rid of includeSubclasses - isn't actually used in practice, makes the codebase uglier, and ClassHierarchy can be
used at query time for most of the same use cases

* Fix field referencing in queries. Should be able to do `$query->search('Text', 'Content')`, not
`$query->search('Text', SiteTree::class . '_Content')` like you have to do now

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
