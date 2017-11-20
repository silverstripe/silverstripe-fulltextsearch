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

```php
// File: mysite/code/MyIndex.php:

use Page;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addFulltextField('Title');
        $this->addFulltextField('Content');
    }
}
```

You can also skip listing all searchable fields, and have the index
figure it out automatically via `addAllFulltextFields()`.

2). Add something to the index (Note: You can also just update an existing document in the CMS. but adding _existing_ objects to the index is connector specific)

```php
$page = Page::create(['Content' => 'Help me. My house is on fire. This is less than optimal.']);
$page->write();
```

Note: There's usually a connector-specific "reindex" task for this.

3). Build a query

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->search('My house is on fire');
```

4). Apply that query to an index

```php
$results = singleton(MyIndex::class)->search($query);
```

Note that for most connectors, changes won't be searchable until _after_ the request that triggered the change.

The return value of a `search()` call is an object which contains a few properties:

 * `Matches`: ArrayList of the current "page" of search results.
 * `Suggestion`: (optional) Any suggested spelling corrections in the original query notation
 * `SuggestionNice`: (optional) Any suggested spelling corrections for display (without query notation)
 * `SuggestionQueryString` (optional) Link to repeat the search with suggested spelling corrections

## Controllers and Templates

In order to render search results, you need to return them from a controller.
You can also drive this through a form response through standard SilverStripe forms.
In this case we simply assume there's a GET parameter named `q` with a search term present.

```php
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

class PageController extends ContentController
{
    private static $allowed_actions = [
        'search',
    ];

    public function search(HTTPRequest $request)
    {
        $query = new SearchQuery();
        $query->search($request->getVar('q'));
        return $this->renderWith([
            'SearchResult' => singleton(MyIndex::class)->search($query)
        ]);
    }
}
```

In your template (e.g. `Page_results.ss`) you can access the results and loop through them.
They're stored in the `$Matches` property of the search return object.

```ss
<% if $SearchResult.Matches %>
    <h2>Results for &quot;{$Query}&quot;</h2>
    <p>Displaying Page $SearchResult.Matches.CurrentPage of $SearchResult.Matches.TotalPages</p>
    <ol>
        <% loop $SearchResult.Matches %>
            <li>
                <h3><a href="$Link">$Title</a></h3>
                <p><% if $Abstract %>$Abstract.XML<% else %>$Content.ContextSummary<% end_if %></p>
            </li>
        <% end_loop %>
    </ol>
<% else %>
    <p>Sorry, your search query did not return any results.</p>
<% end_if %>
```

Please check the [pagination guide](https://docs.silverstripe.org/en/4/developer_guides/templates/how_tos/pagination/)
in the main SilverStripe documentation to learn how to paginate through search results.

## Automatic Index Updates

Every change, addition or removal of an indexed class instance triggers an index update through a
"processor" object. The update is transparently handled through inspecting every executed database query
and checking which database tables are involved in it.

Index updates usually are executed in the same request which caused the index to become "dirty".
For example, a CMS author might have edited a page, or a user has left a new comment.
In order to minimise delays to those users, the index update is deferred until after
the actual request returns to the user, through PHP's `register_shutdown_function()` functionality.

If the [queuedjobs](https://github.com/symbiote/silverstripe-queuedjobs) module is installed,
updates are queued up instead of executed in the same request. Queue jobs are usually processed every minute.
Large index updates will be batched into multiple queue jobs to ensure a job can run to completion within
common execution constraints (memory and time limits). You can check the status of jobs in
an administrative interface under `admin/queuedjobs/`.

## Manual Index Updates

Manual updates are connector specific, please check the connector docs for details.

## Searching Specific Fields

By default, the index searches through all indexed fields.
This can be limited by arguments to the `search()` call.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->search('My house is on fire', [Page::class . '_Title']);
// No results, since we're searching in title rather than page content
$results = singleton(MyIndex::class)->search($query);
```

## Searching Value Ranges

Most values can be expressed as ranges, most commonly dates or numbers.
To search for a range of values rather than an exact match,
use the `SearchQuery_Range` class. The range can include bounds on both sides,
or stay open ended by simply leaving the argument blank.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery_Range;

$query = new SearchQuery();
$query->search('My house is on fire');
// Only include documents edited in 2011 or earlier
$query->filter(Page::class . '_LastEdited', new SearchQuery_Range(null, '2011-12-31T23:59:59Z'));
$results = singleton(MyIndex::class)->search($query);
```

Note: At the moment, the date format is specific to the search implementation.

## Searching Empty or Existing Values

Since there's a type conversion between the SilverStripe database, object properties
and the search index persistence, its often not clear which condition is searched for.
Should it equal an empty string, or only match if the field wasn't indexed at all?
The `SearchQuery` API has the concept of a "missing" and "present" field value for this:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->search('My house is on fire');
// Needs a value, although it can be false
$query->filter(Page::class . '_ShowInMenus', SearchQuery::$present);
$results = singleton(MyIndex::class)->search($query);
```

## Indexing Multiple Classes

An index is a denormalized view of your data, so can hold data from more than one model.
As you can only search one index at a time, all searchable classes need to be included.

```php
// File: mysite/code/MyIndex.php
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\Security\Member;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addClass(Member::class);
        $this->addFulltextField('Content'); // only applies to Page class
        $this->addFulltextField('FirstName'); // only applies to Member class
    }
}
```

## Using Multiple Indexes

Multiple indexes can be created and searched independently, but if you wish to override an existing
index with another, you can use the `$hide_ancestor` config.

```php
use SilverStripe\Assets\File;

class MyReplacementIndex extends MyIndex
{
    private static $hide_ancestor = 'MyIndex';

    public function init()
    {
        parent::init();

        $this->addClass(File::class);
        $this->addFulltextField('Title');
    }
}
```

You can also filter all indexes globally to a set of pre-defined classes if you wish to
prevent any unknown indexes from being automatically included.

```yaml
SilverStripe\FullTextSearch\Search\FullTextSearch:
  indexes:
    - MyReplacementIndex
    - CoreSearchIndex
```

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

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->search(
    'My house is on fire',
    null,
    [
        Page::class . '_Title' => 1.5,
        Page::class . '_Content' => 1.0,
    ]
);
$results = singleton(MyIndex::class)->search($query);
```

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

```php
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;

$this->excludeVariantState([SearchVariantVersioned::class => 'Stage']);
```

Alternatively, you can index draft content, but simply exclude it from searches.
This can be handy to preview search results on unpublished content, in case a CMS author is logged in.
Before constructing your `SearchQuery`, conditionally switch to the "live" stage:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

if (!Permission::check('CMS_ACCESS_CMSMain')) {
    Versioned::set_stage(Versioned::LIVE);
}
$query = new SearchQuery();
// ...
```

### How do I write nested/complex filters?

TODO
