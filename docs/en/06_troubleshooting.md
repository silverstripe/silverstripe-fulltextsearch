# Troubleshooting

## Newly indexed content only shows in searches after a delay

First, check how you're running index operations.
In many cases where the `queuedjobs` module is installed,
saving or publishing a record will create a new index job which needs to complete first.
Solr also distinguishes between adding documents to the indexing,
committing them, and making them available to new searches.
In most cases this happens within a few seconds, but
in sometimes it can take up to a minute due to the
`autoSoftCommit` configuration setting defaults in your `solrconfig.xml`.
To find out more detail, read about
[soft vs. hard commits](https://lucidworks.com/post/understanding-transaction-logs-softcommit-and-commit-in-sorlcloud/). 

## Common gotchas

* By default number-letter boundaries are treated as a word boundary. For example, `A1` is two words - `a` and `1` - when Solr parses the search term.
* Special characters and operators are not correctly escaped
* Multi-word synonym issues
* When Solr indexes are reconfigured and reindexed, their content is trashed and rebuilt

## CWP-specific

* `solrconfig.xml` customisations fail silently
* Developers aren’t able to test raw queries or see output via the 
[Solr admin interface](02_setup.md#solr-admin)
