# Advanced configuration

## Facets

Inside the `SolrIndex->search()` function, the third-party library solr-php-client is used to send data to Solr and parse the response.  Additional information can be pulled from this response and added to your results object for use in templates using the `updateSearchResults()` extension hook.

```php
use My\Namespace\Index\MyIndex;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$index = MyIndex::singleton();
$query = SearchQuery::create()
    ->addSearchTerm('My Term');
$params = [
    'facet' => 'true',
    'facet.field' => 'SiteTree_ClassName',
];
$results = $index->search($query, -1, -1, $params);
```

By adding facet fields into the query parameters, our response object from Solr now contains some additional information that we can add into the results sent to the page.

```php
namespace My\Namespace\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;

class FacetedResultsExtension extends Extension
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
        $facetCounts = ArrayList::create([]);
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

And then apply the extension to your index via `yaml`:

```yaml
My\Namespace\Index\MyIndex:
  extensions:
    - My\Namespace\Extension\FacetedResultsExtension
```

We can now access the facet information inside our templates like so:

```silverstripe
<% if $Results.FacetCounts %>
    <% loop $Results.FacetCounts.Facets %>
        <% loop $Facets %>
            <p>$Name: $Count</p>
        <% end_loop %>
    <% end_loop %>
<% end_if %>
```

## Multiple indexes

Multiple indexes can be created and searched independently, but if you wish to override an existing
index with another, you can use the `$hide_ancestor` config.

```php
use SilverStripe\Assets\File;
use My\Namespace\Index\MyIndex;

class MyReplacementIndex extends MyIndex
{
    private static $hide_ancestor = MyIndex::class;

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

## Analyzers, Tokenizers and Token Filters

When a document is indexed, its individual fields are subject to the analyzing and tokenizing filters that can transform
and normalize the data in the fields. You can remove blank spaces, strip HTML, replace a particular character and much
more as described in the [Solr Wiki](http://wiki.apache.org/solr/AnalyzersTokenizersTokenFilters).

### Synonyms

To add synonym processing at query-time, you can add the `SynonymFilterFactory` as an `Analyzer`:

```php
use SilverStripe\FullTextSearch\Solr\SolrIndex;
use Page;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(Page::class);
        $this->addField('Content');
	    $this->addAnalyzer('Content', 'filter', [
	    	'class' => 'solr.SynonymFilterFactory',
		    'synonyms' => 'synonyms.txt',
		    'ignoreCase' => 'true',
		    'expand' => 'false'
	    ]);
    }
}
```

This generates the following XML schema definition:

```xml
<field name="Page_Content">
  <filter class="solr.SynonymFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="false"/>
</field>
```

In this case, you most likely also want to define your own synonyms list. You can define a mapping in one of two ways:

* A comma-separated list of words. If the token matches any of the words, then all the words in the list are
substituted, which will include the original token.

* Two comma-separated lists of words with the symbol "=>" between them. If the token matches any word on
the left, then the list on the right is substituted. The original token will not be included unless it is also in the
list on the right.

For example:

```text
couch,sofa,lounger
teh => the
small => teeny,tiny,weeny
```

Then you should update your [Solr configuration](03_configuration.md#solr-server-parameters) to include your synonyms
file via the `extraspath` parameter, for example:

```php
use SilverStripe\FullTextSearch\Solr\Solr;

Solr::configure_server([
    'extraspath' => BASE_PATH . '/mysite/Solr/',
    'indexstore' => [
        'mode' => 'file',
        'path' => BASE_PATH . '/.solr',
    ]
]);
```

Will include `/mysite/Solr/synonyms.txt` as your list after a [Solr configure](03_configuration.md#solr-configure)

## Spell check ("Did you mean...")

Solr has various spell checking strategies (see the ["SpellCheckComponent" docs](http://wiki.apache.org/solr/SpellCheckComponent)), all of which are configured through `solrconfig.xml`.
In the default config which is copied into your index, spell checking data is collected from all fulltext fields
(everything you added through `SolrIndex->addFulltextField()`). The values of these fields are collected in a special `_text` field.

```php
use My\Namespace\Index\MyIndex;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$index = MyIndex::singleton();
$query = SearchQuery::create()
    ->addSearchTerm('My Term');
$params = [
    'spellcheck' => 'true',
    'spellcheck.collate' => 'true',
];
$results = $index->search($query, -1, -1, $params);
$results->spellcheck;
```

The built-in `_text` data is better than nothing, but also has some problems: it's heavily processed, for example by
stemming filters which butcher words. So misspelling "Govnernance" will suggest "govern" rather than "Governance".
This can be fixed by aggregating spell checking data in a separate field.

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class MyIndex extends SolrIndex
{
    public function init()
    {
        $this->addCopyField(SiteTree::class . '_Title', 'spellcheckData');
        $this->addCopyField(SomeModel::class . '_Title', 'spellcheckData');
        $this->addCopyField(SiteTree::class . '_Content', 'spellcheckData');
        $this->addCopyField(SomeModel::class . '_Content', 'spellcheckData');
    }

    public function getFieldDefinitions()
    {
        $xml = parent::getFieldDefinitions();

        $xml .= "\n\n\t\t<!-- Additional custom fields for spell checking -->";
        $xml .= "\n\t\t<field name='spellcheckData' type='textSpellHtml' indexed='true' stored='false' multiValued='true' />";

        return $xml;
    }
}
```

Now you need to tell Solr to use our new field for gathering spelling data. In order to customise the spell checking configuration,
create your own `solrconfig.xml` (see [File-based configuration](03_configuration.md#file-based-configuration)). In there, change the following directive:

```xml
<searchComponent name="spellcheck" class="solr.SpellCheckComponent">
    <str name="field">spellcheckData</str>
</searchComponent>
```

Copy the new configuration via a the [`Solr_Configure` task](03_configuration.md#solr-configure), and reindex your data before using the spell checker.

## Highlighting

Solr can highlight the searched terms in context of the matched content, to help users determine the relevancy of results (e.g. in which part of a sentence the term is used). In order to use this feature, the full content of the field to be highlighted needs to be stored in the index,
by declaring it through `addStoredField()`:

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
There's a lot more parameters available for tweaking results detailed on the [Solr reference guide](https://archive.apache.org/dist/lucene/solr/ref-guide/apache-solr-ref-guide-4.10.pdf#page=270).

```php
use My\Namespace\Index\MyIndex;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$index = MyIndex::singleton();
$query = SearchQuery::create()
    ->addSearchTerm('My Term');
$params = [
    'hl' => 'true',
];
$results = $index->search($query, -1, -1, $params);
```

Each result will automatically contain an `Excerpt` property which you can use in your own results template. The searched term is highlighted with an `<em>` tag by default.

> Note: It is recommended to strip out all HTML tags and convert entities on the indexed content,
to avoid matching HTML attributes, and cluttering highlighted content with unparsed HTML.

## Boosting/Weighting

 Results aren't all created equal. Matches in some fields are more important than others; for example, a page `Title` might be considered more relevant to the user than terms in the `Content` field.

 To account for this, a "weighting" (or "boosting") factor can be applied to each searched field. The default value is `1.0`, anything below that will decrease the relevance, anything above increases it. You can get more information on relevancy at the [Solr wiki](http://wiki.apache.org/solr/SolrRelevancyFAQ).

You can manage the boosting in two ways:

### Boosting on query

 To adjust the relative values at the time of querying, pass them in as the third argument to your `addSearchTerm()` call:

 ```php
 use My\Namespace\Index\MyIndex;
 use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
 use Page;

 $query = SearchQuery::create()
     ->addSearchTerm(
         'fire',
         null, // don't limit the classes to search
         [
             Page::class . '_Title' => 1.5,
             Page::class . '_Content' => 1.0,
             Page::class . '_SecretParagraph' => 0.1,
         ]
     );
 $results = MyIndex::singleton()->search($query);
 ```

 This will ensure that `Title` is given higher priority for matches than `Content`, which is well above `SecretParagraph`.

### Boosting on index

Boost values for specific can also be specified directly on the `SolrIndex` class directly.

The following methods can be used to set one or more boosted fields:

* `addBoostedField()` - adds a field with a specific boosted value (defaults to 2)
* `setFieldBoosting()` - if a field has already been added to an index, the boosting
  value can be customised, changed, or reset for a single field.
* `addFulltextField()` A boost can be set for a field using the `$extraOptions` parameter
with the key `boost` assigned to the desired value:

```php
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class SolrSearchIndex extends SolrIndex
{
    public function init()
    {
        $this->addClass(SiteTree::class);

        // The following methods would all add the same boost of 1.5 to "Title"
        $this->addBoostedField('Title', null, [], 1.5);

        $this->addFulltextField('Title', null, [
            'boost' => 1.5,
        ]);

        $this->addFulltextField('Title');
        $this->setFieldBoosting(SiteTree::class . '_Title', 1.5);
    }
}
```

## Indexing related objects

To add a related object to your index.

## Subsites

When you are utilising the [subsites module](https://github.com/silverstripe/silverstripe-subsites) you
may want to add [boosting](#boosting/weighting) to results from the current subsite. To do so, you'll
need to use [eDisMax](https://lucene.apache.org/solr/guide/6_6/the-extended-dismax-query-parser.html)
and the supporting parameters `bq` and `bf`. You should add the following to your `SolrIndex`
extension:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\Subsites\Model\Subsite;

public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = [])) {
    $params = array_merge($params, [
        'defType' => 'edismax', // turn on eDisMax
        'bq' => '_subsite:'.Subsite::currentSubsiteID(),  // boost-query on current subsite ID
        'bf' => '_subsite^2' // double the score of any document with that subsite ID
    ]);

    return parent::search($query, $offset, $limit, $params);
}
```

## Custom field types

Solr supports custom field type definitions which are written to its XML schema. Many standard ones are already included
 in the default schema. As the XML file is generated dynamically, we can add our own types by overloading the template
 responsible for it: `types.ss`.

In the following example, we read our type definitions from a new file `mysite/solr/templates/types.ss` instead:

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

It's usually best to start with the existing definitions, and adjust from there. You can both add your own types and adjust the behaviour of existing definitions.

### Perform filtering on index

An example of something you can achieve with this is to move synonym filtering from performed on query, to being performed on index. To do this, you'd take

```xml
<filter class="solr.SynonymFilterFactory" synonyms="synonyms.txt" ignoreCase="true" expand="true"/>
```

from inside the `<analyzer type="query">` block and move it to the `<analyzer type="index">` block. This can be advantageous as Solr does a better job of processing synonyms at index; however, it does mean that it requires a full Reindex to make a change, which - depending on the size of your site - could be overkill. See [this article](https://nolanlawson.com/2012/10/31/better-synonym-handling-in-solr/) for a good breakdown.

### Searching for words containing numbers

By default, the module is configured to split words containing numbers into multiple tokens. For example, the word "A1" would be interpreted as "A" "1", and since "a" is a common stopword, the term "A1" will be excluded from search.

To allow searches on words containing numeric tokens, you'll need to change the behaviour of the `WordDelimiterFilterFactory` with an overloaded template as described above.  Each instance of `<filter class="solr.WordDelimiterFilterFactory">` needs to include the following attributes and values:

- add `splitOnNumerics="0"` on all `WordDelimiterFilterFactory` fields
- change `catenateNumbers="1"` to `catenateNumbers="0"` on all `WordDelimiterFilterFactory` fields

### Searching for macrons and other Unicode characters

The `ASCIIFoldingFilterFactory` filter converts alphabetic, numeric, and symbolic Unicode characters which are not in the Basic Latin Unicode block (the first 127 ASCII characters) to their ASCII equivalents, if one exists.

By default, this functionality is enabled on the `htmltext` and `text` fieldTypes. If you want it enabled for any other fieldTypes simply find the fields in your overloaded `types.ss` that you want to enable this behaviour in, for example inside the `<fieldType name="textTight">` block, add the following to both its index analyzer and query analyzer records.

```xml
<filter class="solr.ASCIIFoldingFilterFactory"/>
```

## Text extraction

Solr provides built-in text extraction capabilities for PDF and Office documents, and numerous other formats, through
the `ExtractingRequestHandler` API (see [the Solr wiki entry](http://wiki.apache.org/solr/ExtractingRequestHandler).
If you're using a default Solr installation, it's most likely already bundled and set up. But if you plan on running the
Solr server integrated into this module, you'll need to download the libraries and link them first. Run the following
commands from the webroot:

```
wget http://archive.apache.org/dist/lucene/solr/4.10.4/solr-4.10.4.tgz
tar -xvzf solr-4.10.4.tgz
mkdir .solr/PageSolrIndexboot/dist
mkdir .solr/PageSolrIndexboot/contrib
cp solr-4.10.4/dist/solr-cell-4.10.4.jar .solr/PageSolrIndexboot/dist/
cp -R solr-4.10.4/contrib/extraction .solr/PageSolrIndexboot/contrib/
rm -rf solr-4.10.4 solr-4.10.4.tgz
```

Create a custom `solrconfig.xml` (see [File-based configuration](03_configuration.md#file-based-configuration)).

Add the following XML configuration:

```xml
<lib dir="./contrib/extraction/lib/" />
<lib dir="./dist" />
```

Now run a [Solr configure](03_configuration.md#solr-configure). You can use Solr text extraction either directly through
the HTTP API, or through the [Text extraction module](https://github.com/silverstripe-labs/silverstripe-textextraction).
