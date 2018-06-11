# Configuration

## Solr server parameters

Set these values inside your `app/_config.php` - the defaults are shown below:

```php
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'host' => 'localhost', // The host or IP address that Solr is listening on
    'port' => '8983', // The port Solr is listening on
    'path' => '/solr', // The suburl the Solr service is available on
    'version' => '4', // Solr server version - currently only 3 and 4 supported
    'service' => 'Solr4Service', // The class that provides actual communcation to the Solr server
    'extraspath' => BASE_PATH .'/vendor/silverstripe/fulltextsearch/conf/solr/4/extras/', // Absolute path to the folder containing templates used for generating the schema and field definitions
    'templates' => BASE_PATH . '/vendor/silverstripe/fulltextsearch/conf/solr/4/templates/', // Absolute path to the configuration default files, e.g. solrconfig.xml
    'indexstore' => [
        'mode' => NULL, // [REQUIRED] a classname which implements SolrConfigStore, or 'file' or 'webdav'
        'path' => NULL, // [REQUIRED] The (locally accessible) path to write the index configurations to OR The suburl on the Solr host that is set up to accept index configurations via webdav (e.g. BASE_PATH . '/.solr')
        'remotepath' => same as 'path' when using 'file' mode, // The path that the Solr server will read the index configurations from
        'auth' => NULL, // Webdav only - A username:password pair string to use to auth against the webdav server (e.g. solr:solr)
        'port' => '8983' // The port for WebDAV if different from the Solr port
    ]
]);
```

Note: We recommend to put the `indexstore['path']` directory outside of the webroot. If you place it inside of the webroot (as shown in the example), please ensure its contents are not accessible through the webserver.
This can be achieved by server configuration, or (in most configurations) also by marking the folder as hidden via a "dot" prefix.

### Disabling automatic configuration

If you have this module installed but do not have a Solr server running, you can disable the database manipulation
hooks that trigger automatic index updates:

```yaml
SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater:
  enabled: false
```

## Creating an index

An index can essentially be considered a database that contains all of your searchable content. By default, it will store everything in a field called `Content`, which is queried to find your search results. To create an index that you can query, you can define it like so:

```php
use Page;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addFulltextField('Title');
    }
}
```

This will create a new `SolrIndex` called `MyIndex`, and it will store the `Title` field on all `Pages` for searching. To index more than one class,
you simply call `addClass()` multiple times. Fields that you add don't have to be present on all classes in the index, they will only apply to a class
if it is present.

```php
use Page;
use SilverStripe\Security\Member;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

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

You can also skip listing all searchable fields, and have the index figure it out automatically via `addAllFulltextFields()`. This will add any database fields that are `instanceof DBString` to the index. Use this with caution, however, as you may inadvertently return sensitive information - it is often safer to declare your fields explicitly.

Once you've added this file, make sure you run a [Solr configure](#solr-configure) to set up your new index.

## Adding data to an index

Once you have [created your index](#creating-an-index), you can add data to it in a number of ways.

### Reindex the site

Running the [Solr reindex task](#solr-reindex) will crawl your site for classes that match those defined on your index, and add the defined fields to the index for searching. This is the most common method used to build the index the first time, or to perform a full rebuild of the index.

### Publish a page in the CMS

Every change, addition or removal of an indexed class instance triggers an index update through a "processor" object. The update is transparently handled through inspecting every executed database query and checking which database tables are involved in it.

A reindex event will trigger when you make a change in the CMS, via `SearchUpdater::handle_manipulation()`, or `ProxyDBExtension::updateProxy()`. This tracks changes to the database, so any alterations will trigger a reindex. In order to minimise delays to those users, the index update is deferred until after the actual request returns to the user, through PHP's `register_shutdown_function()` functionality.

### Manually

If the situation calls for it, you can add an object to the index directly:

```php
use Page;

$page = Page::create(['Content' => 'Help me. My house is on fire. This is less than optimal.']);
$page->write();
```

Depending on the size of the index and how much content needs to be processed, it could take a while for your search results to be updated, so your newly-updated page may not be available in your search results immediately.

### Queued jobs

If the [Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs/) is installed, updates are queued up instead of executed in the same request. Queued jobs are usually processed every minute. Large index updates will be batched into multiple queued jobs to ensure a job can run to completion within common constraints, such as memory and execution time limits. You can check the status of jobs in an administrative interface under `admin/queuedjobs/`.

### Excluding draft content

By default, the `SearchUpdater` class indexes all available "variant states", so in the case of the `Versioned` extension, both "draft" and "live".
For most cases, you'll want to exclude draft content from your search results.

You can either prevent the draft content from being indexed in the first place, by adding the following to your `SearchIndex::init()` method:

```php
use Page;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use SilverStripe\Versioned\Versioned;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addFulltextField('Title');
        $this->excludeVariantState([SearchVariantVersioned::class => Versioned::DRAFT]);
    }
}
```

Alternatively, you can index draft content, but simply exclude it from searches. This can be handy to preview search results on unpublished content, in case a CMS author is logged in. Before constructing your `SearchQuery`, conditionally switch to the "live" stage:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;

if (!Permission::check('CMS_ACCESS_CMSMain')) {
    Versioned::set_stage(Versioned::LIVE);
}
$query = SearchQuery::create();
// ...
```

## Querying an index

This is where the magic happens. You will construct the search terms and other parameters required to form a `SearchQuery` object, and pass that into a `SearchIndex` to get results.

### Building a `SearchQuery`

First, you'll need to construct a new `SearchQuery` object:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create();
```

You can then alter the `SearchQuery` with a number of methods:

#### `addSearchTerm()`

The simplest - pass through a string to search your index for.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create()
    ->addSearchTerm('fire');
```

You can also limit this to specific fields by passing an array as the second argument, specified in the form of `{table}_{field}`:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use Page;

$query = SearchQuery::create()
    ->addSearchTerm('on fire', [Page::class . '_Title']);
```

#### `addFuzzySearchTerm()`

Pass through a string to search your index for, with "fuzzier" matching - this means that a term like "fishing" would also likely find results containing "fish" or "fisher". Otherwise behaves the same as `addSearchTerm()`.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create()
    ->addFuzzySearchTerm('fire');
```

#### `addClassFilter()`

Only query a specific class in the index, optionally including subclasses.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\PageType\SpecialPage;

$query = SearchQuery::create()
    ->addClassFilter(SpecialPage::class, false); // only return results from SpecialPages, not subclasses
```

#### Searching value ranges

Most values can be expressed as ranges, most commonly dates or numbers. To search for a range of values rather than an exact match,
use the `SearchQuery_Range` class. The range can include bounds on both sides, or stay open-ended by simply leaving the argument blank.
It takes arguments in the form of `SearchQuery_Range::create($start, $end))`:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery_Range;
use My\Namespace\Index\MyIndex;
use Page;

$query = SearchQuery::create()
    ->addSearchTerm('fire')
    // Only include documents edited in 2011 or earlier
    ->addFilter(Page::class . '_LastEdited', SearchQuery_Range::create(null, '2011-12-31T23:59:59Z'));
$results = MyIndex::singleton()->search($query);
```

Note: At the moment, the date format is specific to the search implementation.

#### Searching for empty or existing values

Since there's a type conversion between the SilverStripe database, object properties
and the search index persistence, it's often not clear which condition is searched for.
Should it equal an empty string, or only match if the field wasn't indexed at all?
The `SearchQuery` API has the concept of a "missing" and "present" field value for this:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\Index\MyIndex;
use Page;

$query = SearchQuery::create()
    ->addSearchTerm('fire');
    // Needs a value, although it can be false
    ->addFilter(Page::class . '_ShowInMenus', SearchQuery::$present);
$results = MyIndex::singleton()->search($query);
```

### Querying an index

Once you have your query constructed, you need to run it against your index.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\Index\MyIndex;

$query = SearchQuery::create()->addSearchTerm('fire');
$results = MyIndex::singleton()->search($query);
```

The return value of a `search()` call is an object which contains a few properties:

 * `Matches`: `ArrayList` of the current "page" of search results.
 * `Suggestion`: (optional) Any suggested spelling corrections in the original query notation
 * `SuggestionNice`: (optional) Any suggested spelling corrections for display (without query notation)
 * `SuggestionQueryString` (optional) Link to repeat the search with suggested spelling corrections

## Solr dev tasks

There are two dev/tasks that are central to the operation of the module - `Solr_Configure` and `Solr_Reindex`. You can access these through the web, or via CLI. Running via the web will return "quiet" output by default, but you can increase verbosity by adding `?verbose=1` to the `dev/tasks` URL; CLI will return verbose output by default.

It is often a good idea to run a configure, followed by a reindex, after a code change - for example, after a deployment.

### Solr configure

`dev/tasks/Solr_Configure`

This task will upload configuration to the Solr core, reloading it or creating it as necessary, and generate the schema. This should be run after every code change to your indexes, or after any configuration changes. This will convert the PHP-based abstraction layer into actual Solr XML. Assuming default configuration and the use of the `DefaultIndex`, it will:

- create the directory `BASE_PATH/.solr/DefaultIndex/` if it doesn't already exist
- copy configuration files from `vendor/silverstripe/fulltextsearch/conf/extras` to `BASE_PATH/.solr/DefaultIndex/conf/`
- generate a `schema.xml` in `BASE_PATH/.solr/DefaultIndex/conf/`

This task will overwrite these files every time it is run.

### Solr reindex

`dev/tasks/Solr_Reindex`

This task performs a reindex, which adds all the data specified in the index definition into the index store.

If you have the [Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs/) installed, then this task will create multiple reindex jobs that are processed asynchronously; unless you are in `dev` mode, in which case the index will be processed immediately (see [processor.yml](/_config/processor.yml)). Otherwise, it will run in one process. Often, if you are running it via the web, the request will time out. Usually this means the actually process is still running in the background, but it can be alarming to the user, so bear that in mind.

Internally groups of records are grouped into sizes of 200. You can configure this group sizing by using the `Solr_Reindex.recordsPerRequest` config:

```yaml
SilverStripe\FullTextSearch\Solr\Tasks\Solr_Reindex:
  recordsPerRequest: 150
```

The Solr indexes will be stored as binary files inside your SilverStripe project. You can also copy the `thirdparty/` Solr directory somewhere else, just set the `path` value in `mysite/_config.php` to point to the new location.

## File-based configuration

Many aspects of Solr are configured outside of the `schema.xml` file which SilverStripe generates based on the `SolrIndex` subclass that is defined. For example, stopwords are placed in their own `stopwords.txt` file, and advanced [spellchecking](04_advanced_configuration.md#spell-check-("did-you-mean...")) can be configured in `solrconfig.xml`.

By default, these files are copied from the `fulltextsearch/conf/extras/` directory over to the new index location. In order to use your own files, copy these files into a location of your choosing (for example `mysite/data/solr/`), and tell Solr to use this folder with the `extraspath` [configuration setting](#solr-server-parameters). Run a [`Solr_Configure](#solr-configure) to apply these changes.

You can also define these on an index-by-index basis by defining `SolrIndex->getExtrasPath()`.

## Handling results

In order to render search results, you need to return them from a controller. You can also drive this through a form response through standard SilverStripe forms. In this case we simply assume there's a GET parameter named `q` with a search term present.

```php
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\Index\MyIndex;

class PageController extends ContentController
{
    private static $allowed_actions = [
        'search',
    ];

    public function search(HTTPRequest $request)
    {
        $query = SearchQuery::create()->addSearchTerm($request->getVar('q'));
        return $this->renderWith([
            'SearchResult' => MyIndex::singleton()->search($query)
        ]);
    }
}
```

In your template (e.g. `Page_results.ss`) you can access the results and loop through them. They're stored in the `$Matches` property of the search return object.

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
