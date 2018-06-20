<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Solr\SolrIndex;

class DefaultIndex extends SolrIndex
{

    /**
     * Called during construction, this is the method that builds the structure.
     * Used instead of overriding __construct as we have specific execution order - code that has
     * to be run before _and/or_ after this.
     */
    public function init()
    {
        $this->addClass(SiteTree::class);
        $this->addAllFulltextFields();
    }
}
