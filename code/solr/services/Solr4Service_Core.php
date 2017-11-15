<?php

namespace SilverStripe\FullTextSearch\Solr\Services;

class Solr4Service_Core extends SolrService_Core
{
    /**
     * Replace underlying commit function to remove waitFlush in 4.0+, since it's been deprecated and 4.4 throws errors
     * if you pass it
     */
    public function commit($expungeDeletes = false, $waitFlush = null, $waitSearcher = true, $timeout = 3600)
    {
        if ($waitFlush) {
            user_error('waitFlush must be false when using Solr 4.0+' . E_USER_ERROR);
        }

        $expungeValue = $expungeDeletes ? 'true' : 'false';
        $searcherValue = $waitSearcher ? 'true' : 'false';

        $rawPost = '<commit expungeDeletes="' . $expungeValue . '" waitSearcher="' . $searcherValue . '" />';
        return $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);
    }

    /**
     * @inheritdoc
     * @see Solr4Service_Core::addDocuments
     */
    public function addDocument(
        \Apache_Solr_Document $document,
        $allowDups = false,
        $overwritePending = true,
        $overwriteCommitted = true,
        $commitWithin = 0
    ) {
        return $this->addDocuments(array($document), $allowDups, $overwritePending, $overwriteCommitted, $commitWithin);
    }

    /**
     * Solr 4.0 compat http://wiki.apache.org/solr/UpdateXmlMessages#Optional_attributes_for_.22add.22
     * Remove allowDups, overwritePending and overwriteComitted
     */
    public function addDocuments(
        $documents,
        $allowDups = false,
        $overwritePending = true,
        $overwriteCommitted = true,
        $commitWithin = 0
    ) {
        $overwriteVal = $allowDups ? 'false' : 'true';
        $commitWithin = (int) $commitWithin;
        $commitWithinString = $commitWithin > 0 ? " commitWithin=\"{$commitWithin}\"" : '';

        $rawPost = "<add overwrite=\"{$overwriteVal}\"{$commitWithinString}>";
        foreach ($documents as $document) {
            if ($document instanceof \Apache_Solr_Document) {
                $rawPost .= $this->_documentToXmlFragment($document);
            }
        }
        $rawPost .= '</add>';

        return $this->add($rawPost);
    }
}
