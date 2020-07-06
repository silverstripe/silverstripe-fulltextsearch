# Getting started

## Module scope

### Introduction

This is a module aimed at adding support for standalone fulltext search engines to SilverStripe.

It contains several layers:

 * A fulltext API, ignoring the actual provision of fulltext searching
 * A connector API, providing common code to allow connecting a fulltext searching engine to the fulltext API
 * Some connectors for common fulltext searching engines (currently only [Apache Solr](http://lucene.apache.org/solr/))

### Reasoning

There are several fulltext search engines that work in a similar manner. They build indexes of denormalized data that
are then searched through using some custom query syntax.

Traditionally, fulltext search connectors for SilverStripe have attempted to hide this design, instead presenting
fulltext searching as an extension of the object model. However, the disconnect between the fulltext search engine's
design and the object model meant that searching was inefficient. The abstraction would also often break and it was
hard to then figure out what was going on.

This module instead provides the ability to define those indexes and queries in PHP. The indexes are defined as a 
mapping between the SilverStripe object model and the connector-specific fulltext engine index model. This module then 
interrogates model metadata to build the specific index definition.

It also hooks into SilverStripe framework in order to update the indexes when the models change and connectors then 
convert those index and query definitions into fulltext engine specific code.

The intent of this module is not to make changing fulltext search engines seamless. Where possible this module provides
common interfaces to fulltext engine functionality, abstracting out common behaviour. However, each connector also
offers its own extensions, and there is some behaviour (such as getting the fulltext search engines installed, 
configured and running) that each connector deals with itself, in a way best suited to that search engine's design.

## Quick start

If you are running on a Linux-based system, you can get up and running quickly with the quickstart script, like so:

```bash
composer require silverstripe/fulltextsearch --prefer-source && vendor/bin/fulltextsearch_quickstart
```

This will:

- Install the required Java SDK (using `apt-get` or `yum`)
- Install Solr 4
- Set up a daemon to run Solr on startup
- Start Solr
- Configure Solr in your `_config.php` (and create one if you don't have one)
- Create a DefaultIndex
- Run a [Solr Configure](03_configuration.md#solr-configure) and a [Solr Reindex](03_configuration.md#solr-reindex)

If you have the [CMS module](https://github.com/silverstripe/silverstripe-cms) installed, you will be able to simply add
 `$SearchForm` to your template to add a Solr search form. Default configuration is added via the
 [`ContentControllerExtension`](/src/Solr/Control/ContentControllerExtension.php) and alternative
 [`SearchForm`](/src/Solr/Forms/SearchForm.php). With the
 [Simple theme](https://github.com/silverstripe-themes/silverstripe-simple), this is in the
 [`Header`](https://github.com/silverstripe-themes/silverstripe-simple/blob/master/templates/Includes/Header.ss#L10-L15)
 by default.

Ensure that you _don't_ have `SilverStripe\ORM\Search\FulltextSearchable::enable()` set in `_config.php`, as the 
`SearchForm` action provided by that class will conflict.

You can override the default template with a new one at `templates/Layout/Page_results_solr.ss`.
