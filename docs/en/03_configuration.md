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

Depending on the size of the index and how much content needs to be processed, it could take a while for your search results to be updated, so your newly-updated page may not be available in your search results immediately. This approach is typically not recommended.

### Queued jobs

If the [Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs/) is installed, updates are queued up instead of executed in the same request. Queued jobs are usually processed every minute. Large index updates will be batched into multiple queued jobs to ensure a job can run to completion within common constraints, such as memory and execution time limits. You can check the status of jobs in an administrative interface under `admin/queuedjobs/`.

### Draft content

By default, the `SearchUpdater` class attempts to index all available "variant states", except for draft content.
Draft content is excluded by default via calls to SearchableService::variantStateExcluded().

Excluding draft content was a new default added in 3.7.0.  Prior to that, draft content was previously indexed by
 default and could be excluded fron the index by adding the following to the `SearchIndex::init()` method:

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

If required, you can opt-out of the secure default and index draft content, but simply exclude it from searches.
Read the inline documentation within SearchableService.php for more details on how to do this.
This can be handy to preview search results on unpublished content, in case a CMS author is logged in.
Before constructing your `SearchQuery`, conditionally switch to the "live" stage.

### Adding DataObjects

If you create a class that extends `DataObject` (and not `Page`) then it won't be automatically added to the search
index. You'll have to make some changes to add it in. The `DataObject` class will require the following minimum code 
to render properly in the search results:

* `Link()` needs to return the URL to follow from the search results to actually view the object.
* `Name` (as a DB field) will be used as the result title.
* `Abstract` (as a DB field) will show under the search result title.
* `ShowInSearch` (as a DB field) or `getShowInSearch()` is recommended to allow the optional exclusion of DataObjects from being added to the search index.  If omitted, then all DataObjects of this type will be added to the search index.

So with that, you can add your class to your index:

```php
use My\Namespace\Model\SearchableDataObject;
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use Page;

class MySolrSearchIndex extends SolrIndex {

    public function init()
    {
        $this->addClass(SearchableDataObject::class);
        $this->addClass(Page::class);
        $this->addAllFulltextFields();
    }
}
```

Once you've created the above classes and run the [solr dev tasks](#solr-dev-tasks) to tell Solr about the new index 
you've just created, this will add `SearchableDataObject` and the text fields it has to the index. Now when you search 
on the site using `MySolrSearchIndex->search()`, the `SearchableDataObject` results will show alongside normal `Page`
results.

### ShowInSearch and getShowInSearch() filtering

The fulltextsearch module checks the value of `ShowInSearch` on each object it operates against, and if this evaluates
to `false`, the object is excluded from the index / results. You can implement a `getShowInSearch` method on your
DataObject to control the way this is computed. This check happens in two places:

a) When attempting to add the object to the search index (or update it)
b) Before returning results from the search index. Note: this only applies to Solr 4 implementations.

The second check is an additional layer to ensure that a result is excluded if the evaluated response changes between
index and query time. For example, a getShowInSearch() implementation that filters out objects after a certain date
might return `true` when the object is added to the index, but `false` when a user later performs a search.

This filtering is applied to all Page (SiteTree) and File records since they have a ShowInSearch database column.
This will also be applied to any DataObjects that have a ShowInSearch database column or a getShowInSearch() function.

This is a compulsory check and there is no opt-out available.

Note: If you implement a custom getShowInSearch() method on a Page, the database column 'ShowInSearch' will not be used
and the 'Show In Search?' settings in the CMS admin found under Page > Settings will no longer work. Either incorporate
the ShowInSearch column in your getShowInSearch() logic, or remove the field from the CMS to minimise confusion.

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

Many aspects of Solr are configured outside of the `schema.xml` file which SilverStripe generates based on the `SolrIndex` subclass that is defined. For example, stopwords are placed in their own `stopwords.txt` file, and advanced [spellchecking](05_advanced_configuration.md#spell-check-("did-you-mean...")) can be configured in `solrconfig.xml`.

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

```silverstripe
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
