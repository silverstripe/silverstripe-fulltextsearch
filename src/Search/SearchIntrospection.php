<?php
namespace SilverStripe\FullTextSearch\Search;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;

/**
 * Some additional introspection tools that are used often by the fulltext search code
 */
class SearchIntrospection
{
    protected static $ancestry = array();

    /**
     * Check if class is subclass of (a) the class in $of, or (b) any of the classes in the array $of
     * @static
     * @param  $class
     * @param  $of
     * @return bool
     */
    public static function is_subclass_of($class, $of)
    {
        $ancestry = isset(self::$ancestry[$class]) ? self::$ancestry[$class] : (self::$ancestry[$class] = ClassInfo::ancestry($class));
        return is_array($of) ? (bool)array_intersect($of, $ancestry) : array_key_exists($of, $ancestry);
    }

    protected static $hierarchy = array();

    /**
     * Get all the classes involved in a DataObject hierarchy - both super and optionally subclasses
     *
     * @static
     * @param string $class - The class to query
     * @param bool $includeSubclasses - True to return subclasses as well as super classes
     * @param bool $dataOnly - True to only return classes that have tables
     * @return array - Integer keys, String values as classes sorted by depth (most super first)
     */
    public static function hierarchy($class, $includeSubclasses = true, $dataOnly = false)
    {
        $key = "$class!" . ($includeSubclasses ? 'sc' : 'an') . '!' . ($dataOnly ? 'do' : 'al');

        if (!isset(self::$hierarchy[$key])) {
            $classes = array_values(ClassInfo::ancestry($class));
            if ($includeSubclasses) {
                $classes = array_unique(array_merge($classes, array_values(ClassInfo::subclassesFor($class))));
            }

            $idx = array_search(DataObject::class, $classes);
            if ($idx !== false) {
                array_splice($classes, 0, $idx+1);
            }

            if ($dataOnly) {
                foreach ($classes as $i => $class) {
                    if (!DataObject::getSchema()->classHasTable($class)) {
                        unset($classes[$i]);
                    }
                }
            }

            self::$hierarchy[$key] = $classes;
        }

        return self::$hierarchy[$key];
    }

    /**
     * Add classes to list, keeping only the parent when parent & child are both in list after add
     */
    public static function add_unique_by_ancestor(&$list, $class)
    {
        // If class already has parent in list, just ignore
        if (self::is_subclass_of($class, $list)) {
            return;
        }

        // Strip out any subclasses of $class already in the list
        $children = ClassInfo::subclassesFor($class);
        $list = array_diff($list, $children);

        // Then add the class in
        $list[] = $class;
    }

    /**
     * Does this class, it's parent (or optionally one of it's children) have the passed extension attached?
     */
    public static function has_extension($class, $extension, $includeSubclasses = true)
    {
        foreach (self::hierarchy($class, $includeSubclasses) as $relatedclass) {
            if ($relatedclass::has_extension($extension)) {
                return true;
            }
        }
        return false;
    }
}
