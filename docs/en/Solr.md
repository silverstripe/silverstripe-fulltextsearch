# Solr connector for SilverStripe fulltextsearch module

## Introduction

The fulltextsearch module includes support for connecting to Solr.

It works with Solr in multi-core mode. It needs to be able to update Solr configuration files, and has modes for
doing this by direct file access (when Solr shares a server with SilverStripe) and by WebDAV (when it's on a different
server).

See the helpful [Solr Tutorial](http://lucene.apache.org/solr/4_5_1/tutorial.html), for more on cores
and querying.

## Requirements

Since Solr is Java based, it requires Java 1.5 or greater installed.

When you're installing it yourself, it also requires a servlet container such as Tomcat, Jetty, or Resin. For
development testing there is a standalone version that comes bundled with Jetty (see below).

See the official [Solr installation docs](http://wiki.apache.org/solr/SolrInstall) for more information.

Note that these requirements are for the Solr server environment, which doesn't have to be the same physical machine
as the SilverStripe webhost.

## Installation (Local)

### Get the Solr server

```
composer require silverstripe/fulltextsearch-localsolr
```

### Start the server (via CLI, in a separate terminal window or background process)

```
cd fulltextsearch-localsolr/server/
java -jar start.jar
```

### Configure the fulltextsearch Solr component to use the local server

Configure Solr in file mode. The 'path' directory has to be writeable
by the user the Solr search server is started with (see below).

```php
// File: mysite/_config.php:
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'host' => 'localhost',
    'indexstore' => [
        'mode' => 'file',
        'path' => BASE_PATH . '/.solr'
    ]
]);
```

All possible parameters incl optional ones with example values:

```php
// File: mysite/_config.php:
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'host' => 'localhost', // default: localhost | The host or IP Solr is listening on
    'port' => '8983', // default: 8983 | The port Solr is listening on
    'path' => '/solr', // default: /solr | The suburl the solr service is available on
    'version' => '4', // default: 4 | Solr server version - currently only 3 and 4 supported
    'service' => 'Solr4Service', // default: depends on version, Solr3Service for 3, Solr4Service for 4 | the class that provides actual communcation to the Solr server
    'extraspath' => BASE_PATH .'/fulltextsearch/conf/solr/4/extras/', // default: <basefolder>/fulltextsearch/conf/solr/{version}/extras/ | Absolute path to the folder containing templates which are used for generating the schema and field definitions.
    'templates' => BASE_PATH . '/fulltextsearch/conf/solr/4/templates/', // default: <basefolder>/fulltextsearch/conf/solr/{version}/templates/ | Absolute path to the configuration default files, e.g. solrconfig.xml
    'indexstore' => [
        'mode' => 'file', // a classname which implements SolrConfigStore, or 'file' or 'webdav'
        'path' => BASE_PATH . '/.solr', // The (locally accessible) path to write the index configurations to OR The suburl on the solr host that is set up to accept index configurations via webdav
        'remotepath' => '/opt/solr/config', // default (file mode only): same as 'path' above | The path that the Solr server will read the index configurations from
        'auth' => 'solr:solr', // default: none | Webdav only - A username:password pair string to use to auth against the webdav server
        'port' => '80' // default: same as solr port | The port for WebDAV if different from the Solr port
    ]
]);
```

Note: We recommend to put the `indexstore.path` directory outside of the webroot.
If you place it inside of the webroot (as shown in the example),
please ensure its contents are not accessible through the webserver.
This can be achieved by server configuration, or (in most configurations)
also by marking the folder as hidden via a "dot" prefix.

## Configuration

### Create an index

```php
// File: mysite/code/MyIndex.php:
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addAllFulltextFields();
    }
}
```

### Create the index schema

The PHP-based index definition is an abstraction layer for the actual Solr XML configuration.
In order to create or update it, you need to run the `Solr_Configure` task.

```
vendor/bin/sake dev/tasks/Solr_Configure
```

Based on the sample configuration above, this command will do the following:

- Create a `<BASE_PATH>/.solr/MyIndex` folder
- Copy configuration files from `vendor/silverstripe/fulltextsearch/conf/extras/` to `<BASE_PATH>/.solr/MyIndex/conf`
- Generate a `schema.xml`, and place it it in `<BASE_PATH>/.solr/MyIndex/conf`

If you call the task with an existing index folder,
it will overwrite all files from their default locations,
regenerate the `schema.xml`, and ask Solr to reload the configuration.

You can use the same command for updating an existing schema,
which will automatically apply without requiring a Solr server restart.

### Reindex

After configuring Solr, you have the option to add your existing
content to its indices. Run the following command:

```
vendor/bin/sake dev/tasks/Solr_Reindex
```

This will delete and rebuild all indices. Depending on your data,
this can take anywhere from minutes to hours.
Keep in mind that the normal mode of updating indices is
based on ORM manipulations of the underlying data.
For example, calling `$myPage->write()` will automatically
update the index entry for this record (and all its variants).

This task has the following options:

- `verbose`: Debug information

Internally, depending on what job processing backend you have configured (such as queuedjobs)
individual tasks for re-indexing groups of records may either be performed behind the scenes
as crontasks, or via separate processes initiated by the current request.

Internally groups of records are grouped into sizes of 200. You can configure this
group sizing by using the `Solr_Reindex.recordsPerRequest` config.

```yaml
SilverStripe\FullTextSearch\Solr\Tasks\Solr_Reindex:
  recordsPerRequest: 150
```

Note: The Solr indexes will be stored as binary files inside your SilverStripe project.
You can also copy the `thirdparty/` solr directory somewhere else,
just set the `path` value in `mysite/_config.php` to point to the new location.

You can also run the reindex task through a web request.
By default, the web request won't receive any feedback while its running.
Depending on your PHP and web server configuration,
the web request itself might time out, but the reindex continues anyway.
This is possible because the actual index operations are run as separate
PHP sub-processes inside the main web request.

### File-based configuration (solrconfig.xml etc)

Many aspects of Solr are configured outside of the `schema.xml` file
which SilverStripe generates based on the index PHP file.
For example, stopwords are placed in their own `stopwords.txt` file,
and spell checks are configured in `solrconfig.xml`.

By default, these files are copied from the `fulltextsearch/conf/extras/`
directory over to the new index location. In order to use your own files,
copy these files into a location of your choosing (for example `mysite/data/solr/`),
and tell Solr to use this folder with the `extraspath` configuration setting.

```php
// mysite/_config.php
use SilverStripe\Control\Director;
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    // ...
    'extraspath' => Director::baseFolder() . '/mysite/data/solr/',
]);
```

Please run the `Solr_Configure` task for the changes to take effect.

Note: You can also define those on an index-by-index basis by
implementing `SolrIndex->getExtrasPath()`.

### Custom Types

Solr supports custom field type definitions which are written to its XML schema.
Many standard ones are already included in the default schema.
As the XML file is generated dynamically, we can add our own types
by overloading the template responsible for it: `types.ss`.

In the following example, we read out type definitions
from a new file `mysite/solr/templates/types.ss` instead:

```php
use SilverStripe\Control\Director;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function getTypes()
    {
        return $this->renderWith(Director::baseFolder() . '/mysite/solr/templates/types.ss');
    }
}
```

#### Searching for words containing numbers

By default, the fulltextmodule is configured to split words containing numbers into multiple tokens. For example, the word "A1" would be interpreted as "A" "1"; since "a" is a common stopword, the term "A1" will be excluded from search.

To allow searches on words containing numeric tokens, you'll need to update your overloaded template to change the behaviour of the  WordDelimiterFilterFactory.  Each instance of `<filter class="solr.WordDelimiterFilterFactory">` needs to include the following attributes and values:

* add splitOnNumerics="0" on all WordDelimiterFilterFactory fields
* change catenateOnNumbers="1" on all WordDelimiterFilterFactory fields

Update your index to point to your overloaded template using the method described above.

#### Searching for macrons and other Unicode characters

The "ASCIIFoldingFilterFactory" filter converts alphabetic, numeric, and symbolic Unicode characters which are not in the Basic Latin Unicode block (the first 127 ASCII characters) to their ASCII equivalents, if one exists.

Find the fields in your overloaded `types.ss` that you want to enable this behaviour in. EG:

```xml
<fieldType name="htmltext" class="solr.TextField" ... >
```

Add the following to both its index analyzer and query analyzer records.

```xml
<filter class="solr.ASCIIFoldingFilterFactory"/>
```

Update your index to point to your overloaded template using the method described above.

### Spell Checking ("Did you mean...")

Solr has various spell checking strategies (see the ["SpellCheckComponent" docs](http://wiki.apache.org/solr/SpellCheckComponent)), all of which are configured through `solrconfig.xml`.
In the default config which is copied into your index,
spell checking data is collected from all fulltext fields
(everything you added through `SolrIndex->addFulltextField()`).
The values of these fields are collected in a special `_text` field.

```php
use SilverStripe\FullTextSearch\Search\Queries;

$index = new MyIndex();
$query = new SearchQuery();
$query->search('My Term');
$params = [
    'spellcheck' => 'true',
    'spellcheck.collate' => 'true',
];
$results = $index->search($query, -1, -1, $params);
$results->spellcheck
```

The built-in `_text` data is better than nothing, but also has some problems:
Its heavily processed, for example by stemming filters which butcher words.
So misspelling "Govnernance" will suggest "govern" rather than "Governance".
This can be fixed by aggregating spell checking data in a separate

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        // ...
        $this->addCopyField(SiteTree::class . '_Title', 'spellcheckData');
        $this->addCopyField(SomeModel::class . '_Title', 'spellcheckData');
        $this->addCopyField(SiteTree::class . '_Content', 'spellcheckData');
        $this->addCopyField(SomeModel::class . '_Content', 'spellcheckData');
    }

    // ...
    public function getFieldDefinitions()
    {
        $xml = parent::getFieldDefinitions();

        $xml .= "\n\n\t\t<!-- Additional custom fields for spell checking -->";
        $xml .= "\n\t\t<field name='spellcheckData' type='textSpellHtml' indexed='true' stored='false' multiValued='true' />";

        return $xml;
    }
}
```

Now you need to tell solr to use our new field for gathering spelling data.
In order to customize the spell checking configuration,
create your own `solrconfig.xml` (see "File-based configuration").
In there, change the following directive:

```xml
<!-- ... -->
<searchComponent name="spellcheck" class="solr.SpellCheckComponent">
    <!-- ... -->
    <str name="field">spellcheckData</str>
</searchComponent>
```

Don't forget to copy the new configuration via a call to the `Solr_Configure`
task, and reindex your data before using the spell checker.

### Limiting search fields

Solr has a way of specifying which fields to search on. You specify these
fields as a parameter to `SearchQuery`.

In the following example, we're telling Solr to *only* search the
`Title` and `Content` fields. Note that the fields must be specified in
the search parameters as "composite fields", which means they should be
specified in the form of `{table}_{field}`.

These fields are defined in the schema.xml file that gets sent to Solr.

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->classes = [
    ['class' => Page::class, 'includeSubclasses' => true],
];
$query->search('someterms', [SiteTree::class . '_Title', SiteTree::class . '_Content']);
$result = singleton(SolrSearchIndex::class)->search($query, -1, -1);

// the request to Solr would be:
// q=(SiteTree_Title:Lorem+OR+SiteTree_Content:Lorem)
```

### Configuring boosts

There are several ways in which you can configure boosting on search fields or terms.

#### Boosting on search query

Solr has a way of specifying which fields should be boosted as a parameter to `SearchQuery`.

This means if you boost a certain field, search query matches on that field will be considered
higher relevance than other fields with matches, and therefore those results will be closer
to the top of the results.

In this example, we enter "Lorem" as the search term, and boost the `Content` field:

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = new SearchQuery();
$query->classes = [
    ['class' => 'Page', 'includeSubclasses' => true],
];
$query->search('Lorem', null, [SiteTree::class . '_Content' => 2]);
$result = singleton(SolrSearchIndex::class)->search($query, -1, -1);

// the request to Solr would be:
// q=SiteTree_Content:Lorem^2
```

More information on [relevancy on the Solr wiki](http://wiki.apache.org/solr/SolrRelevancyFAQ).

### Boosting on index fields

Boost values for specific can also be specified directly on the `SolrIndex` class directly.

The following methods can be used to set one or more boosted fields:

* `SolrIndex::addBoostedField` Adds a field with a specific boosted value (defaults to 2)
* `SolrIndex::setFieldBoosting` If a field has already been added to an index, the boosting
  value can be customised, changed, or reset for a single field.
* `SolrIndex::addFulltextField` A boost can be set for a field using the `$extraOptions` parameter
with the key `boost` assigned to the desired value.

For example:

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrSearchIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(SiteTree::class);
        $this->addAllFulltextFields();
        $this->addFilterField('ShowInSearch');
        $this->addBoostedField('Title', null, [], 1.5);
        $this->setFieldBoosting(SiteTree::class . '_SearchBoost', 2);
    }

}
```

### Custom Types

Solr supports custom field type definitions which are written to its XML schema.
Many standard ones are already included in the default schema.
As the XML file is generated dynamically, we can add our own types
by overloading the template responsible for it: `types.ss`.

In the following example, we read out type definitions
from a new file `mysite/solr/templates/types.ss` instead:

```php
use SilverStripe\Control\Director;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function getTemplatesPath()
    {
        return Director::baseFolder() . '/mysite/solr/templates/';
    }
}
```

### Highlighting

Solr can highlight the searched terms in context of the matched content,
to help users determine the relevancy of results (e.g. in which part of a sentence
the term is used). In order to use this feature, the full content of the
field to be highlighted needs to be stored in the index,
by declaring it through `addStoredField()`.

```php
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addAllFulltextFields();
        $this->addStoredField('Content');
    }
}
```

To search with highlighting enabled, you need to pass in a custom query parameter.
There's a lot more parameters to tweak results on the [Solr Wiki](http://wiki.apache.org/solr/HighlightingParameters).

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$index = new MyIndex();
$query = new SearchQuery();
$query->search('My Term');
$results = $index->search($query, -1, -1, ['hl' => 'true']);
```

Each result will automatically contain an "Excerpt" property
which you can use in your own results template.
The searched term is highlighted with an `<em>` tag by default.

Note: It is recommended to strip out all HTML tags and convert entities on the indexed content,
to avoid matching HTML attributes, and cluttering highlighted content with unparsed HTML.

### Adding additional information into search results

Inside the SolrIndex::search() function, the third-party library solr-php-client
is used to send data to Solr and parse the response.  Additional information can
be pulled from this response and added to your results object for use in templates
using the `updateSearchResults()` extension hook.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$index = new MyIndex();
$query = new SearchQuery();
$query->search('My Term');
$results = $index->search($query, -1, -1, [
    'facet' => 'true',
    'facet.field' => 'SiteTree_ClassName',
]);
```

By adding facet fields into the query parameters, our response object from Solr
now contains some additional information that we can add into the results sent
to the page.

```php
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;

class MyResultsExtension extends Extension
{
    /**
     * Adds extra information from the solr-php-client repsonse
     * into our search results.
     * @param ArrayData $results The ArrayData that will be used to generate search
     *        results pages.
     * @param stdClass $response The solr-php-client response object.
     */
    public function updateSearchResults($results, $response)
    {
        if (!isset($response->facet_counts) || !isset($response->facet_counts->facet_fields)) {
            return;
        }
        $facetCounts = ArrayList::create(array());
        foreach($response->facet_counts->facet_fields as $name => $facets) {
            $facetDetails = ArrayData::create([
                'Name' => $name,
                'Facets' => ArrayList::create([]),
            ]);

            foreach($facets as $facetName => $facetCount) {
                $facetDetails->Facets->push(ArrayData::create([
                    'Name' => $facetName,
                    'Count' => $facetCount,
                ]));
            }
            $facetCounts->push($facetDetails);
        }
        $results->setField('FacetCounts', $facetCounts);
    }
}
```

We can now access the facet information inside our templates.

### Adding Analyzers, Tokenizers and Token Filters

When a document is indexed, its individual fields are subject to the analyzing and tokenizing filters that can transform and normalize the data in the fields. For example — removing blank spaces, removing html code, stemming, removing a particular character and replacing it with another
(see [Solr Wiki](http://wiki.apache.org/solr/AnalyzersTokenizersTokenFilters)).

Example: Replace synonyms on indexing (e.g. "i-pad" with "iPad")

```php
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addField('Content');
        $this->addAnalyzer('Content', 'filter', ['class' => 'solr.SynonymFilterFactory']);
    }
}
```

Generates the following XML schema definition:

```xml
<field name="Page_Content" ...>
  <filter class="solr.SynonymFilterFactory" synonyms="syn.txt" ignoreCase="true" expand="false"/>
</field>
```

### Text Extraction

Solr provides built-in text extraction capabilities for PDF and Office documents,
and numerous other formats, through the `ExtractingRequestHandler` API
(see http://wiki.apache.org/solr/ExtractingRequestHandler).
If you're using a default Solr installation, it's most likely already
bundled and set up. But if you plan on running the Solr server integrated
into this module, you'll need to download the libraries and link the first.

```
wget http://archive.apache.org/dist/lucene/solr/3.1.0/apache-solr-3.1.0.tgz
mkdir tmp
tar -xvzf apache-solr-3.1.0.tgz
mkdir .solr/PageSolrIndexboot/dist
mkdir .solr/PageSolrIndexboot/contrib
cp apache-solr-3.1.0/dist/apache-solr-cell-3.1.0.jar .solr/PageSolrIndexboot/dist/
cp -R apache-solr-3.1.0/contrib/extraction .solr/PageSolrIndexboot/contrib/
rm -rf apache-solr-3.1.0 apache-solr-3.1.0.tgz
```

Create a custom `solrconfig.xml` (see "File-based configuration").
Add the following XML configuration.

```xml
<lib dir="./contrib/extraction/lib/" />
<lib dir="./dist" />
```

Now apply the configuration:

```
vendor/bin/sake dev/tasks/Solr_Configure
```

Now you can use Solr text extraction either directly through the HTTP API,
or indirectly through the ["textextraction" module](https://github.com/silverstripe-labs/silverstripe-textextraction).

## Adding DataObject classes to Solr search

If you create a class that extends `DataObject` (and not `Page`) then it won't be automatically added to the search
index. You'll have to make some changes to add it in.

So, let's take an example of `StaffMember`:

```php
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;

class StaffMember extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(255)',
        'Abstract' => 'Text',
        'PhoneNumber' => 'Varchar(50)',
    ];

    public function Link($action = 'show')
    {
        return Controller::join_links('my-controller', $action, $this->ID);
    }

    public function getShowInSearch()
    {
        return 1;
    }
}
```

This `DataObject` class has the minimum code necessary to allow it to be viewed in the site search.

`Link()` will return a URL for where a user goes to view the data in more detail in the search results.
`Name` will be used as the result title, and `Abstract` the summary of the staff member which will show under the
search result title.
`getShowInSearch` is required to get the record to show in search, since all results are filtered by `ShowInSearch`.

So with that, let's create a new class called `MySolrSearchIndex`:

```php
use StaffMember;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MySolrSearchIndex extends SolrIndex {

    public function init()
    {
        $this->addClass(SiteTree::class);
        $this->addClass(StaffMember::class);

        $this->addAllFulltextFields();
        $this->addFilterField('ShowInSearch');
    }
}
```

This is a copy/paste of the existing configuration but with the addition of `StaffMember`.

Once you've created the above classes and run `flush=1`, access `dev/tasks/Solr_Configure` and `dev/tasks/Solr_Reindex`
to tell Solr about the new index you've just created. This will add `StaffMember` and the text fields it has to the
index. Now when you search on the site using `MySolrSearchIndex->search()`,
the `StaffMember` results will show alongside normal `Page` results.


## Debugging

### Using the web admin interface

You can visit `http://localhost:8983/solr`, which will show you a list
to the admin interfaces of all available indices.
There you can search the contents of the index via the native SOLR web interface.

It is possible to manually replicate the data automatically sent
to Solr when saving/publishing in SilverStripe,
which is useful when debugging front-end queries,
see `thirdparty/fulltextsearch/server/silverstripe-solr-test.xml`.

```
java -Durl=http://localhost:8983/solr/MyIndex/update/ -Dtype=text/xml -jar post.jar silverstripe-solr-test.xml
```

## FAQ

### How do I use date ranges where dates might not be defined?

The Solr index updater only includes dates with values,
so the field might not exist in all your index entries.
A simple bounded range query (`<field>:[* TO <date>]`) will fail in this case.
In order to query the field, reverse the search conditions and exclude the ranges you don't want:

```php
// Wrong: Filter will ignore all empty field values
$myQuery->filter('fieldname', new SearchQuery_Range('*', 'somedate'));

// Better: Exclude the opposite range
$myQuery->exclude('fieldname', new SearchQuery_Range('somedate', '*'));
```
