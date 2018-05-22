# Querying an index

This is where the magic happens. You will construct the search terms and other parameters required to form a `SearchQuery` object, and pass that into a `SearchIndex` to get results.

## Building a `SearchQuery`

First, you'll need to construct a new `SearchQuery` object:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create();
```

You can then alter the `SearchQuery` with a number of methods:

### `addSearchTerm()`

The simplest - pass through a string to search your index for.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create()
    ->addSearchTerm('fire');
```

You can also limit this to specific fields by passing an array as the second argument:

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use Page;

$query = SearchQuery::create()
    ->addSearchTerm('on fire', [Page::class . '_Title']);
```

### `addFuzzySearchTerm()`

Pass through a string to search your index for, with "fuzzier" matching - this means that a term like "fishing" would also likely find results containing "fish" or "fisher". Otherwise behaves the same as `addSearchTerm()`.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

$query = SearchQuery::create()
    ->addFuzzySearchTerm('fire');
```

### `addClassFilter()`

Only query a specific class in the index, optionally including subclasses.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\PageType\SpecialPage;

$query = SearchQuery::create()
    ->addClassFilter(SpecialPage::class, false); // only return results from SpecialPages, not subclasses
```

## Querying an index

Once you have your query constructed, you need to run it against your index.

```php
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use My\Namespace\Index\MyIndex;

$query = SearchQuery::create()->addSearchTerm('fire');
$results = singleton(MyIndex::class)->search($query);
```

The return value of a `search()` call is an object which contains a few properties:

 * `Matches`: `ArrayList` of the current "page" of search results.
 * `Suggestion`: (optional) Any suggested spelling corrections in the original query notation
 * `SuggestionNice`: (optional) Any suggested spelling corrections for display (without query notation)
 * `SuggestionQueryString` (optional) Link to repeat the search with suggested spelling corrections
