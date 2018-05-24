# Creating an index

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

Once you've added this file, make sure you run a [Solr configure](./33_dev_tasks.md) to set up your new index.
