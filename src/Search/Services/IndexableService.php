<?php

namespace SilverStripe\FullTextSearch\Search\Services;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Tests\MySQLDatabaseTest\Data;

/**
 * Checks if a DataObject is publically viewable thus able to be added or retrieved from a publically searchable index
 * Caching results because these checks may be done multiple times as there a few different code paths that search
 * results might follow in real-world search implementations
 */
class IndexableService
{

    use Injectable;
    use Extensible;

    protected $cache = [];

    public function clearCache(): void
    {
        $this->cache = [];
    }

    public function isIndexable(DataObject $obj): bool
    {
        // check if is a valid DataObject that has been persisted to the database
        if (is_null($obj) || !$obj->ID) {
            return false;
        }

        $key = $this->getCacheKey($obj);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $value = true;

        // This will also call $obj->getShowInSearch() if it exists
        if (isset($obj->ShowInSearch) && !$obj->ShowInSearch) {
            $value = false;
        }

        $this->extend('updateIsIndexable', $obj, $value);
        $this->cache[$key] = $value;
        return $value;
    }

    protected function getCacheKey(DataObject $obj): string
    {
        $key = $obj->ClassName . '_' . $obj->ID;
        $this->extend('updateCacheKey', $obj, $key);
        return $key;
    }
}
