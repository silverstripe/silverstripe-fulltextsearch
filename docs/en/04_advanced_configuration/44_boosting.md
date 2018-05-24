# Boosting/Weighting

Results aren't all created equal. Matches in some fields are more important
than others; for example, a page `Title` might be considered more relevant to the user than terms in the `Content` field.

To account for this, a "weighting" (or "boosting") factor can be applied to each searched field. The default value is `1.0`, anything below that will decrease the relevance, anything above increases it.

To adjust the relative values, pass them in as the third argument to your `addSearchTerm()` call:

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
$results = singleton(MyIndex::class)->search($query);
```

This will ensure that `Title` is given higher priority for matches than `Content`, which is well above `SecretParagraph`.
