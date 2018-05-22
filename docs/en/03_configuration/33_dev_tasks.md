# Solr dev tasks

There are two dev/tasks that are central to the operation of the module - `Solr_Configure` and `Solr_Reindex`. You can access these through the web, or via CLI. It is often a good idea to run a configure, followed by a reindex, after a code change - for example, after a deployment.

## Solr configure

`dev/tasks/Solr_Configure`

This task will upload configuration to the Solr core, reloading it or creating it as necessary. This should be run after every code change to your indexes, or configuration changes.

## Solr reindex

`dev/tasks/Solr_Reindex`

This task performs a reindex, which adds all the data specified in the index definition into the index store.

If you have the [Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs/) installed, then this task will create multiple reindex jobs that are processed asynchronously. Otherwise, it will run in one process. Often, if you are running it via the web, the request will time out. Usually this means the actually process is still running in the background, but it can be alarming to the user.

If instead you run the task via the command line, you will see verbose output as the reindexing progresses.
