# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [2.2.0]

* FIX Indexes with custom index names that don't match the classname were breaking
* BUG Fix versioned writes where subtables have no fields key
* BUGFIX: Fixed issue where the $id variable would be overridden in sub sequent iterations of the derived fields loop
* adding stemming support
* BUG fix issues with search variants applying to more than one class
* API adding stemming support
* FIX: Fix initial dev/build on PDO Database.

## [2.1.1]

* Converted to PSR-2
* FIX: remove parameters from function calls
* Added standard code of conduct
* Added standard editor config
* Updated license
* Added standard gitattributes
* MINOR: Don't include Hamcrest globally so it doesn't conflict with PHPUnit

## [2.1.0]

* 3.2 Compatibility
* Add ss 3.2 and PHP 5.6 to CI
* Added standard Scrutinizer config
* Added standard Travis config

## [2.0.0]

* Fix highlight support when querying by fields (or boosting fields)
* Updating travis provisioner
* Added docs about controller and template usage
* API Enable boosted fields to be specified on the index
* BUG Prevent subsites breaking solrindexversionedtest
* Enable indexes to upload custom config
* API Additional support for custom copy_fields
* API QueuedJob support for Solr_Reindex

## [1.1.0]

* API Solr_Reindex uses configured SearchUpdater instead of always doing a direct write
* Fix class limit on delete query in SolrIndex
* Regression in SearchUpdater_ObjectHandler
* API Separate searchupdate / commit into separate queued-jobs
* API Only allow one scheduled commit job at a time

## [1.0.6]

* Make spelling suggestions more useful
* BUG Add missing addStoredFields method

## [1.0.5]

* BUG Fix Solr 4.0 compatibility issue
* BUG Fix test case not elegantly failing on missing phockito
* API SearchUpdateQueuedJobProcessor now uses batching
* Fix many_many fieldData bug
* Adding tests for SearchIndex::fieldData()
* Add a no-op query to prevent database timeouts during a long reindex

## [1.0.4]

* BUG Patch up the information leak of debug information.
* FIX: will work for postgreSQL

## [1.0.3]

Users upgrading from 1.0.2 or below will need to run the Solr_Reindex task to refresh
each SolrIndex. This is due to a change in record IDs, which are now generated from
the base class of each DataObject, rather than the instance class, as well as fixes
to integration with the subsites module.

Developers working locally should be aware that by default, all indexes will be updated
in realtime when the environment is in dev mode, rather than attempting to queue these
updates with the queued jobs module (if installed).

### Bugfixes

 * BUG Fix old indexing storing against the incorrect class key
 * [Don't rely on MySQL ordering of index->getAdded()](https://github.com/silverstripe-labs/silverstripe-fulltextsearch/commit/4b51393e014fc4c0cc8e192c74eb4594acaca605)

### API

 * [API Disable queued processing for development environments](https://github.com/silverstripe-labs/silverstripe-fulltextsearch/commit/71fc359b3711cf5b9429d86da0f1e0b20bd43dee)
