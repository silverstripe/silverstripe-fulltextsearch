<?php

namespace SilverStripe\FullTextSearch\Search\Indexes;

use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;

/**
 * A search index that just records actions. Useful for testing
 */
abstract class SearchIndex_Recording extends SearchIndex
{
    public $added = [];
    public $deleted = [];
    public $committed = false;

    public function reset()
    {
        $this->added = [];
        $this->deleted = [];
        $this->committed = false;
    }

    public function add($object)
    {
        $res = [];

        $res['ID'] = $object->ID;

        foreach ($this->getFieldsIterator() as $name => $field) {
            $val = $this->_getFieldValue($object, $field);
            $res[$name] = $val;
        }

        $this->added[] = $res;
    }

    public function getAdded($fields = [])
    {
        $res = [];

        foreach ($this->added as $added) {
            $filtered = [];
            foreach ($fields as $field) {
                if (isset($added[$field])) {
                    $filtered[$field] = $added[$field];
                }
            }
            $res[] = $filtered;
        }

        return $res;
    }

    public function delete($base, $id, $state)
    {
        $this->deleted[] = ['base' => $base, 'id' => $id, 'state' => $state];
    }

    public function commit()
    {
        $this->committed = true;
    }

    public function getIndexName()
    {
        return static::class;
    }

    public function getIsCommitted()
    {
        return $this->committed;
    }

    public function getService()
    {
        // Causes commits to the service to be redirected back to the same object
        return $this;
    }
}
