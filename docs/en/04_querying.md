# Querying

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

You can also limit this to specific fields by passing an array as the second argument, specified in the form of `{table}_{field}`:

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

## Searching value ranges

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

### How do I use date ranges where dates might not be defined?

The Solr index updater only includes dates with values, so the field might not exist in all your index entries. A simple bounded range query (`<field>:[* TO <date>]`) will fail in this case. In order to query the field, reverse the search conditions and exclude the ranges you don't want:

```php
// Wrong: Filter will ignore all empty field values
$query->addFilter('fieldname', SearchQuery_Range::create('*', 'somedate'));

// Right: Exclude the opposite range
$query->addExclude('fieldname', SearchQuery_Range::create('somedate', '*'));
```

Note: At the moment, the date format is specific to the search implementation.

## Empty or existing values

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

## Executing your query

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

## Complex Filtering with Criteria
 
### Filtering related Objects
 
* `SearchCriteriaInterface`: Interface for `SearchCriterion` and `SearchCriteria` classes.
* `SearchCriterion`: An object containing a single field filter (target field, comparison value, comparison type).
* `SearchCriteria`: An object containing a collection of `SearchCriterion` and/or `SearchCriteria` with conjunctions (IE: `AND`, `OR`) between each.
* `SearchQueryWriter`: A class used to generate a query string based on a `SearchCriterion`.
* `SearchAdapterInterface`: An Interface for our SearchAdapters. This adapter will control what `SearchQueryWriter` is used for each `SearchCriteria`.
 
### General usage
 
We need 3 things to create a `SearchCriterion`:
 
* **`Target`**: EG the field in our Search Index that we want to filter against.
* **`Value`**: The value we want to use for comparison.
* **`Comparison`**: The type of comparison (EG: `EQUAL`, `IN`, etc).
 
All currently supported comparisons can be found as constants in `SearchCriterion`.
 
### Creating a new `SearchCriterion`
 
#### Method 1a and 1b
 
```php
// `EQUAL` is the default comparison for `SearchCriterion`, so no third param is required.
$criterion = new SearchCriterion('Product_Title', 'My Product');
 
// Or use the `create` static method.
$criterion = SearchCriterion::create('Product_Title', 'My Product');
```
 
### Creating a new `SearchCriteria`
 
`SearchCriteria` has a property called `$clauses` which is a collection of `SearchCriterion` (above) and/or `SearchCriteria` (allowing for infinite nesting of clauses), along with the conjunction used between each clause (IE: `AND`, `OR`). We want to build up our `SearchCriteria` by adding to it's `$clauses` collection.
 
`SearchCriteria` can either be passed an object that implements `SearchCriteriaInterface`, or it can be passed the `Target`, `Value`, and `Comparison` (like above).
 
#### Method 1
 
Instantiate a new `SearchCriteria` by providing an already instantiated `SearchCriterion` object. This `$criterion` will be added as the first item in the `$clauses` collection.
 
```php
$criteria = SearchCriteria::create($criterion);
```
 
#### Method 2
 
Instantiate a new `SearchCriteria` objects and define the `Target`, `Value`, and `Comparison`. `SearchCriteria` will create a new `SearchCriterion` object based on the values, and add it to the `$clauses` collection.
 
```php
$criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);
```
 
### Adding additional `SearchCriterion` to our `SearchCriteria`
 
When you want to add more complexity to your `SearchCriteria`, there are two methods available:
 
* `addAnd`: Add a new `SearchCriterion` or `SearchCriteria` with an `AND` conjunction.
* `addOr`: Add a new `SearchCriterion` or `SearchCriteria` with an `OR` conjunction.
 
#### Method 1
 
Use method chaining to create a `SearchCriterion` with two clauses.
 
```php
// Filter by products with stock that are in either of these 3 categories.
$criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN)
    ->addAnd('Product_Stock', 0, AbstractSearchCriterion::GREATER_THAN);
```
 
#### Method 2
 
Systematically add clauses to your already instantiated `SearchCriteria`.
 
```php
// Filter by products in either of these 3 categories.
$criteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);
 
... other stuff
 
// Filter by products with stock.
$criteria->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);
```
 
### Adding multiple levels of filtering to our `SearchCriteria`
 
`SearchCriteria` also allows you to pass in other `SearchCriteria` objects as you instantiate it and as you use the `addAnd` and `addOr` methods.
 
```php
// Filter by products that are in either of these 3 categories with stock.
$stockCategoryCriteria = SearchCriteria::create('Product_CatID', array(21, 24, 25), AbstractCriterion::IN)
    ->addAnd('Product_Stock', 0, AbstractSearchCriterion::GREATER_THAN);
 
// Filter by products in Category ID  1 with stock over 5.
$legoCriteria = SearchCriteria::create('Product_CatID', 1, AbstractCriterion::EQUAL)
    ->addAnd('Product_Stock', 5, AbstractSearchCriterion::GREATER_THAN);
 
// Combine the two criteria with an `OR` conjunction
$criteria = SearchCriteria::create($stockCategoryCriteria)
    ->addOr($legoCriteria);
```
 
### Adding `SearchCriteria` to our `SearchQuery`
 
Our `SearchQuery` class now has a property called `$criteria` which holds all of our `SearchCriteria`. You can add new `SearchCriteria` by using `SearchQuery::filterBy()`.
 
#### Method 1
 
Pass in an already instantiated `SearchCriteria` object. If you implemented complex filtering (above), you will probably need to follow this method - fully creating your `SearchCriteria` first, and then passing it to the `SearchQuery`.
 
```php
$query->filterBy($criteria);
```
 
#### Method 2a
Where basic (single level) filtering is ok, the `SearchQuery::filterBy()` method can be used to create your `SearchCriterion` and `SearchCriteria` object.
 
```php
$query->filterBy('Product_CatID', array(21, 24, 25), AbstractCriterion::IN);
```
 
#### Method 2b
The `filterBy()` method will return the **current** `SearchCriteria`, this allows you to method chain the `addAnd` and `addOr` methods.
 
```php
// Filter by products with stock that are in either of these 3 categories.
$searchQuery->filterBy('Product_CategoryID', array(21, 24, 25), AbstractCriterion::IN)
    ->addAnd('Product_StockLevel', 0, AbstractCriterion::GREATER_THAN);
```
 
Each item in the `$criteria` collection are treated with an `AND` conjunction (matching current `filter`/`exclude` functionality).
 
### Search Query Writers
 
Provided are 3 different `SearchQueryWriter`s for Solr:
 
* `SolrSearchQueryWriter_Basic`
* `SolrSearchQueryWriter_In`
* `SolrSearchQueryWriter_Range`
 
When these Writers are provided a `SearchCriterion`, they will generate the desired query string.
 
### Search Adapters
 
Search Adapters need to provide the following information:
 
* What is the search engine's conjunction strings? (EG: are they "AND" and "OR", or are they "&&" and "||", etc).
* What is the desired comparison container string? (EG: "**+(** query here **)**") for Solr).
* Most importantly - how to generate the query string from a `SearchCriterion`.
 
The `SolrSearchAdapter` uses `SearchQueryWriter`s (above) to generate query strings from a `SearchCriterion`.
 
### Customising your `SearchCriterion`/`SearchQueryWriter`
 
If you find that you do not want your `SearchCriterion` being parsed by one of the default `SearchQueryWriter`s (for whatever reason), you can optionally pass your own `SearchQueryWriter` to your `SearchCriterion` either as the **fourth parameter** when instantiating it, or by calling `setSearchQueryWriter()`.
 
If this value is set, then the (default Solr) Adapter will always use the provided `SearchQueryWriter`, rather than deciding for itself.
 
This should allow you to have full control over how your query strings are being generated if the default `SearchQueryWriter`s are not cutting it for you.