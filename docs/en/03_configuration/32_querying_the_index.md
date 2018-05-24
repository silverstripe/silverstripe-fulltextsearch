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

### Searching value ranges

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
$results = singleton(MyIndex::class)->search($query);
```

Note: At the moment, the date format is specific to the search implementation.

### Searching for empty or existing values

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
$results = singleton(MyIndex::class)->search($query);
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
