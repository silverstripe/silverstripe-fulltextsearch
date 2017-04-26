<?php

namespace SilverStripe\FullTextSearch\Tests\SolrIndexVersionedTest;

if (!class_exists('\Phockito')) {
    return;
}

\Phockito::include_hamcrest(false);

class SolrDocumentMatcher extends \Hamcrest_BaseMatcher
{
    protected $properties;

    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    public function describeTo(\Hamcrest_Description $description)
    {
        $description->appendText('\Apache_Solr_Document with properties '.var_export($this->properties, true));
    }

    public function matches($item)
    {
        if (! ($item instanceof \Apache_Solr_Document)) {
            return false;
        }

        foreach ($this->properties as $key => $value) {
            if ($item->{$key} != $value) {
                return false;
            }
        }

        return true;
    }
}
