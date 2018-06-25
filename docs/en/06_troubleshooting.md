# Troubleshooting

## Common gotchas

* By default number-letter boundaries are treated as a word boundary. For example, `A1` is two words - `a` and `1` - when Solr parses the search term.
* Special characters and operators are not correctly escaped
* Multi-word synonym issues
* When Dolr indexes are reconfigured and reindexed, their content is trashed and rebuilt

### CWP-specific

* `solrconfig.xml` customisations fail silently
* Developers aren’t able to test raw queries or see output via the 
[Solr admin interface](02_setup.md#solr-admin)
