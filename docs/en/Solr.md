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
$myQuery->addFilter('fieldname', new SearchQuery_Range('*', 'somedate'));

// Better: Exclude the opposite range
$myQuery->addExclude('fieldname', new SearchQuery_Range('somedate', '*'));
```
