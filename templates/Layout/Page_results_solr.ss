<div id="Content" class="searchResults">
    <h1>$Title</h1>

    <% if $Query %>
        <p class="searchQuery"><%t SearchQuery 'You searched for' %> &quot;{$Query}&quot;</p>
    <% end_if %>

    <% if $Results.Suggestion %>
        <p class="spellCheck"><%t DidYouMean 'Did you mean' %> <a href="{$Link}SearchForm?Search=$Results.SuggestionQueryString">$Results.SuggestionNice</a>?</p>
    <% end_if %>

    <% if $Results.Matches %>
        <ul id="SearchResults">
            <% loop $Results.Matches %>
                <li>
                    <h4>
                        <a href="$Link">
                            <% if $MenuTitle %>
                                $MenuTitle
                            <% else %>
                                $Title
                            <% end_if %>
                        </a>
                    </h4>
                    <p><% if $Abstract %>$Abstract.XML<% else %>$Content.ContextSummary<% end_if %></p>
                    <a class="readMoreLink" href="$Link" title="Read more about &quot;{$Title}&quot;"><%t ReadMore 'Read more about' %> &quot;{$Title}&quot;...</a>
                </li>
            <% end_loop %>
        </ul>
    <% else %>
        <p><%t NoResults 'Sorry, your search query did not return any results.' %></p>
    <% end_if %>

    <% if $Results.Matches.MoreThanOnePage %>
        <div id="PageNumbers">
            <div class="pagination">
                <% if $Results.Matches.NotFirstPage %>
                    <a class="prev" href="$Results.Matches.PrevLink" title="View the previous page">&larr;</a>
                <% end_if %>
                <span>
                    <% loop $Results.Matches.Pages %>
                        <% if $CurrentBool %>
                            $PageNum
                        <% else %>
                            <a href="$Link" title="View page number $PageNum" class="go-to-page">$PageNum</a>
                        <% end_if %>
                    <% end_loop %>
                </span>
                <% if $Results.Matches.NotLastPage %>
                    <a class="next" href="$Results.Matches.NextLink" title="View the next page">&rarr;</a>
                <% end_if %>
            </div>
            <p><%t Page 'Page' %> $Results.Matches.CurrentPage <%t of 'of' %> $Results.Matches.TotalPages</p>
        </div>
    <% end_if %>
</div>
