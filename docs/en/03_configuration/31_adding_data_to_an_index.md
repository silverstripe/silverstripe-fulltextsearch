# Adding data to an index

Once you have [created your index](./30_creating_an_index.md), you can add data to it in a number of ways.

## Reindex the site

Running the [Solr reindex task](./33_dev_tasks.md) will crawl your site for classes that match those defined on your index, and add the defined fields to the index for searching. This is the most common method used to build the index the first time, or to perform a full rebuild of the index.

## Publish a page in the CMS

Every change, addition or removal of an indexed class instance triggers an index update through a "processor" object. The update is transparently handled through inspecting every executed database query and checking which database tables are involved in it.

A reindex event will trigger when you make a change in the CMS, via `SearchUpdater::handle_manipulation()`, or `ProxyDBExtension::updateProxy()`. This tracks changes to the database, so any alterations will trigger a reindex. In order to minimise delays to those users, the index update is deferred until after the actual request returns to the user, through PHP's `register_shutdown_function()` functionality.

## Manually

If the situation calls for it, you can add an object to the index directly:

```php
use Page;

$page = Page::create(['Content' => 'Help me. My house is on fire. This is less than optimal.']);
$page->write();
```

Depending on the size of the index and how much content needs to be processed, it could take a while for your search results to be updated, so your newly-updated page may not be available in your search results immediately.

## Queued jobs

If the [Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs/) is installed, updates are queued up instead of executed in the same request. Queued jobs are usually processed every minute. Large index updates will be batched into multiple queued jobs to ensure a job can run to completion within common constraints, such as memory and execution time limits. You can check the status of jobs in an administrative interface under `admin/queuedjobs/`.
