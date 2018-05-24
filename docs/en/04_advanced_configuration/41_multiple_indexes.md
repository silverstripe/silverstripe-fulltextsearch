# Using multiple indexes

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
