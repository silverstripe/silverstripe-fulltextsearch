# Handling results

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
            'SearchResult' => singleton(MyIndex::class)->search($query)
        ]);
    }
}
```

In your template (e.g. `Page_results.ss`) you can access the results and loop through them. They're stored in the `$Matches` property of the search return object.

```ss
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
