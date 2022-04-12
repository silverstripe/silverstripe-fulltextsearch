<?php

namespace SilverStripe\FullTextSearch\Search\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariantVersioned;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

/**
 * Checks if a DataObject is publicly viewable, thus able to be added to or retrieved from a publicly searchable index.
 * Results are cached because these checks may be run multiple times, as there a few different code paths that search
 * results might follow in real-world search implementations.
 */
class SearchableService
{

    use Injectable;
    use Extensible;
    use Configurable;

    /**
     * Skip the canView() check at a class level to increase performance of search reindex.
     * Be careful as this may lead to content showing in search results that should not be there such as non-public,
     * cms-user-only content.  This may potentially happen via edge cases such as skipping checks where subclasses
     * are involved.
     *
     * This has no effect on when search results as canView() must still be run there
     *
     * @var array namespaced classes to skip canView() check on search reindex
     */
    private static $indexing_canview_exclude_classes = [];

    /**
     * Configurable value to index draft content.  Default is true for better security.
     *
     * If you need to index draft content, then view README.md for instructions
     *
     * @var bool
     */
    private static $variant_state_draft_excluded = true;

    /**
     * Non-persistant memory cache that only lasts the lifetime of the request
     *
     * @var array
     */
    private $cache = [];

    /**
     * Clears the internal cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Check to exclude a variant state
     *
     * @param array $state
     * @return bool
     */
    public function variantStateExcluded(array $state): bool
    {
        if (self::config()->get('variant_state_draft_excluded') && $this->isDraftVariantState($state)) {
            return true;
        }
        return false;
    }

    /**
     * Check if a state array represents a draft variant
     *
     * @param array $state
     * @return bool
     */
    private function isDraftVariantState(array $state): bool
    {
        $class = SearchVariantVersioned::class;
        return isset($state[$class]) && $state[$class] == Versioned::DRAFT;
    }

    /**
     * Used during search reindex
     *
     * This is considered the primary layer of protection
     *
     * @param DataObject $obj
     * @return bool
     */
    public function isIndexable(DataObject $obj): bool
    {
        return $this->isSearchable($obj, true);
    }

    /**
     * Used when retrieving search results
     *
     * This is considered the secondary layer of protection
     *
     * It's important to still have this layer in conjuction with the index layer as non-searchable results may be
     * in the search index because:
     * a) they were added to the index pre-fulltextsearch 3.7 and a reindex to purge old records was never run, OR
     * b) the DataObject has a non-deterministic canView() check such as `return $date <= $dateOfIndex;`
     *
     * @param DataObject $obj
     * @return bool
     */
    public function isViewable(DataObject $obj): bool
    {
        return $this->isSearchable($obj, false);
    }

    /**
     * Checks and caches whether the given DataObject can be indexed. This is determined by two factors:
     * - Whether the ShowInSearch property / getShowInSearch() method evaluates to true
     * - Whether the canView method evaluates to true against an anonymous user (optional, can be disabled)
     *
     * @param DataObject $obj
     * @param bool $indexing
     * @return bool
     */
    private function isSearchable(DataObject $obj, bool $indexing): bool
    {
        // check if is a valid DataObject that has been persisted to the database
        if (is_null($obj) || !$obj->ID) {
            return false;
        }

        $key = $this->getCacheKey($obj, $indexing);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $value = true;

        // ShowInSearch check
        // This will also call $obj->getShowInSearch() if it exists
        if (isset($obj->ShowInSearch) && !$obj->ShowInSearch) {
            $value = false;
        }

        // canView() checker
        if ($value) {
            $objClass = $obj->getClassName();
            if ($indexing) {
                // Anonymous member canView() for indexing
                if (!$this->classSkipsCanViewCheck($objClass)) {
                    $value = Member::actAs(null, function () use ($obj) {
                        return (bool) $obj->canView();
                    });
                }
            } else {
                // Current member canView() check for retrieving search results
                $value = (bool) $obj->canView();
            }
        }
        $this->extend('updateIsSearchable', $obj, $indexing, $value);
        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * @param DataObject $obj
     * @param bool $indexing
     * @return string
     */
    private function getCacheKey(DataObject $obj, bool $indexing): string
    {
        $type = $indexing ? 'indexing' : 'viewing';
        // getUniqueKey() requires silverstripe/framework 4.6
        $uniqueKey = '';
        if (method_exists($obj, 'getUniqueKey')) {
            try {
                $uniqueKey = $obj->getUniqueKey();
            } catch (\Exception $e) {
                $uniqueKey = '';
            }
        }
        if (!$uniqueKey) {
            $uniqueKey = sprintf('%s-%s', $obj->ClassName, $obj->ID);
        }
        $key = sprintf('%s-%s', $type, $uniqueKey);
        $this->extend('updateCacheKey', $obj, $indexing, $key);
        return $key;
    }

    /**
     * @param string $class
     * @return bool
     */
    private function classSkipsCanViewCheck(string $class): bool
    {
        $skipClasses = self::config()->get('indexing_canview_exclude_classes') ?? [];
        if (empty($skipClasses)) {
            return false;
        }
        if (in_array($class, $skipClasses ?? [])) {
            return true;
        }
        foreach ($skipClasses as $skipClass) {
            if (in_array($skipClass, class_parents($class) ?? [])) {
                return true;
            }
        }
        return false;
    }
}
