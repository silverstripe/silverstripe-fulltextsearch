<?php

namespace SilverStripe\FullTextSearch\Search\Services;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Checks if a DataObject is publicly viewable, thus able to be added to or retrieved from a publicly searchable index.
 * Results are cached because these checks may be run multiple times, as there a few different code paths that search
 * results might follow in real-world search implementations.
 */
class IndexableService
{
    use Injectable;
    use Extensible;

    protected $cache = [];

    /**
     * Clears the internal indexable cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Checks and caches whether the given DataObject can be indexed. This is determined by two factors:
     *
     * - Whether the ShowInSearch property / getShowInSearch method evaluates to true
     * - Whether the canView method evaluates to true against an anonymous user (optional, can be disabled)
     *
     * @see FullTextSearch::isCanViewCheckDisabledForClass()
     * @param DataObject $obj
     * @return bool
     */
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

        // Run canView check as anonymous user if it hasn't been disabled for this DataObject
        $objClass = $obj->getClassName();
        if (!FullTextSearch::isCanViewCheckDisabledForClass($objClass)) {
            $value = $value && Member::actAs(null, function () use ($obj, $objClass) {
                // Attempt to use optimised permission checker if present.
                // NOTE: This reduces the scope of the check to DB-based permissions (custom canView logic is ignored)
                if (method_exists($objClass, 'getPermissionChecker')) {
                    return $objClass::singleton()->getPermissionChecker()->canView($obj->ID);
                }

                return $obj->canView();
            });
        }

        $this->extend('updateIsIndexable', $obj, $value);
        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * Generates a unique key to cache each DataObject with. Can be extended via updateCacheKey.
     *
     * @param DataObject $obj
     * @return string
     */
    protected function getCacheKey(DataObject $obj): string
    {
        $key = $obj->ClassName . '_' . $obj->ID;
        $this->extend('updateCacheKey', $obj, $key);
        return $key;
    }
}
