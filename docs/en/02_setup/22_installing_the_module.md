# Installing the module

## Disabling automatic configuration

If you have this module installed but do not have a Solr server running, you can disable the database manipulation
hooks that trigger automatic index updates:

```yaml
---
Name: mysitesearch
---
SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater:
  enabled: false
```
