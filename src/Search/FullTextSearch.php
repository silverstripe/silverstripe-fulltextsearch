<?php

namespace SilverStripe\FullTextSearch\Search;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;
use ReflectionClass;

/**
 * Base class to manage active search indexes.
 */
class FullTextSearch
{
    protected static $all_indexes = null;

    protected static $indexes_by_subclass = array();

    /**
     * Optional list of index names to limit to. If left empty, all subclasses of SearchIndex
     * will be used
     *
     * @var array
     * @config
     */
    private static $indexes = array();

    /**
     * Get all the instantiable search indexes (so all the user created indexes, but not the connector or library level
     * abstract indexes). Can optionally be filtered to only return indexes that are subclasses of some class
     *
     * @static
     * @param string $class - Class name to filter indexes by, so that all returned indexes are subclasses of provided
     * class
     * @param bool $rebuild - If true, don't use cached values
     */
    public static function get_indexes($class = null, $rebuild = false)
    {
        if ($rebuild) {
            self::$all_indexes = null;
            self::$indexes_by_subclass = array();
        }

        if (!$class) {
            if (self::$all_indexes === null) {
                // Get declared indexes, or otherwise default to all subclasses of SearchIndex
                $classes = Config::inst()->get(__CLASS__, 'indexes')
                    ?: ClassInfo::subclassesFor(SearchIndex::class);

                $hidden = array();
                $candidates = array();
                foreach ($classes as $class) {
                    // Check if this index is disabled
                    $hides = $class::config()->hide_ancestor;
                    if ($hides) {
                        $hidden[] = $hides;
                    }

                    // Check if this index is abstract
                    $ref = new ReflectionClass($class);
                    if (!$ref->isInstantiable()) {
                        continue;
                    }

                    $candidates[] = $class;
                }

                if ($hidden) {
                    $candidates = array_diff($candidates, $hidden);
                }

                // Create all indexes
                $concrete = array();
                foreach ($candidates as $class) {
                    $concrete[$class] = singleton($class);
                }

                self::$all_indexes = $concrete;
            }

            return self::$all_indexes;
        } else {
            if (!isset(self::$indexes_by_subclass[$class])) {
                $all = self::get_indexes();

                $valid = array();
                foreach ($all as $indexclass => $instance) {
                    if (is_subclass_of($indexclass, $class)) {
                        $valid[$indexclass] = $instance;
                    }
                }

                self::$indexes_by_subclass[$class] = $valid;
            }

            return self::$indexes_by_subclass[$class];
        }
    }

    /**
     * Sometimes, like when in tests, you want to restrain the actual indexes to a subset
     *
     * Call with one argument - an array of class names, index instances or classname => indexinstance pairs (can be
     * mixed).
     * Alternatively call with multiple arguments, each of which is a class name or index instance
     *
     * From then on, fulltext search system will only see those indexes passed in this most recent call.
     *
     * Passing in no arguments resets back to automatic index list
     *
     * Alternatively you can use `FullTextSearch.indexes` to configure a list of indexes via config.
     */
    public static function force_index_list()
    {
        $indexes = func_get_args();

        // No arguments = back to automatic
        if (!$indexes) {
            self::get_indexes(null, true);
            return;
        }

        // Arguments can be a single array
        if (is_array($indexes[0])) {
            $indexes = $indexes[0];
        }

        // Reset to empty first
        self::$all_indexes = array();
        self::$indexes_by_subclass = array();

        // And parse out alternative type combos for arguments and add to allIndexes
        foreach ($indexes as $class => $index) {
            if (is_string($index)) {
                $class = $index;
                $index = singleton($class);
            }
            if (is_numeric($class)) {
                $class = get_class($index);
            }

            self::$all_indexes[$class] = $index;
        }
    }
}
