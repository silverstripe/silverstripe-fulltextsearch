## Running newer versions of Solr natively

Assumptions: [installing Solr 5.x on an Ubuntu machine](https://www.digitalocean.com/community/tutorials/how-to-install-solr-5-2-1-on-ubuntu-14-04). Other distros are untested but may follow a similar process. If running Solr natively without the localsolr module, the generated .solr directory needs to be readable and writeable by the solr user. This may differ from your webserver user, or the user running the Solr_Configure command. If the command results in an error and you do not have a `/path/to/.solr/<IndexName>/data` folder, check that the solr user is able to read your /path/to/.solr/<IndexName>/conf/solrconfig.xml file. Failure to read this file will result in a core index not being created.

To use this version in SilverStripe, include the following in mysite/_config.php:
```
Solr::configure_server(array(
    'host' => 'localhost',
    'port' => 8983,
    'version' => 5,
    'indexstore' => array(
        'mode' => 'file',
        'path' => '/home/solr/.solr'    //readable by local `solr` user
    )
));
```

If you get an error on Solr_Configure, try running the command with `sudo` and changing the owner of the resulting folder to `solr`. If you can repeat with sudo without an error, you should be able to run Solr_Reindex

## Migrating to later versions of Solr

### 4 to 5 (and probably 6)
Credit to @zarocknz and @firesphere for suggested changes and approaches.

#### solrconfig.xml
- update luceneMatchVersion to 6.0
- update JsonUpdateRequestHandler to UpdateRequestHandler
- update CSVUpdateRequestHandler to UpdateRequestHandler
- remove `<requestHandler name="/admin" .../>`
#### types.ss
- remove all occurrences of `enablePositionIncrements="true"
