<?php

namespace SilverStripe\FullTextSearch\Search\Variants;

use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Utils\CombinationsArrayIterator;

/**
 * A Search Variant handles decorators and other situations where the items to reindex or search through are modified
 * from the default state - for instance, dealing with Versioned or Subsite
 */
abstract class SearchVariant
{
    use Configurable;

    /**
     * Whether this variant is enabled
     *
     * @config
     * @var boolean
     */
    private static $enabled = true;

    public function __construct()
    {
    }

    /*** OVERRIDES start here */

    /**
     * Variants can provide any functions they want, but they _must_ override these functions
     * with specific ones
     */

    /**
     * Return false if there is something missing from the environment (probably a
     * not installed module) that means this variant can't apply to any class
     */
    public function appliesToEnvironment()
    {
        return $this->config()->get('enabled');
    }

    /**
     * Return true if this variant applies to the passed class & subclass
     */
    abstract public function appliesTo($class, $includeSubclasses);

    /**
     * Return the current state
     */
    abstract public function currentState();
    /**
     * Return all states to step through to reindex all items
     */
    abstract public function reindexStates();
    /**
     * Activate the passed state
     */
    abstract public function activateState($state);

    /**
     * Apply this variant to a search query
     *
     * @param SearchQuery $query
     * @param SearchIndex $index
     */
    abstract public function alterQuery($query, $index);

    /*** OVERRIDES end here*/

    /** Holds a cache of all variants */
    protected static $variants = null;
    /** Holds a cache of the variants keyed by "class!" "1"? (1 = include subclasses) */
    protected static $class_variants = array();

    /**
     * Returns an array of variants.
     *
     * With no arguments, returns all variants
     *
     * With a classname as the first argument, returns the variants that apply to that class
     * (optionally including subclasses)
     *
     * @static
     * @param string $class - The class name to get variants for
     * @param bool $includeSubclasses - True if variants should be included if they apply to at least one subclass of $class
     * @return array - An array of (string)$variantClassName => (Object)$variantInstance pairs
     */
    public static function variants($class = null, $includeSubclasses = true)
    {
        if (!$class) {
            // Build up and cache a list of all search variants (subclasses of SearchVariant)
            if (self::$variants === null) {
                $classes = ClassInfo::subclassesFor(static::class);

                $concrete = array();
                foreach ($classes as $variantclass) {
                    $ref = new ReflectionClass($variantclass);
                    if ($ref->isInstantiable()) {
                        $variant = singleton($variantclass);
                        if ($variant->appliesToEnvironment()) {
                            $concrete[$variantclass] = $variant;
                        }
                    }
                }

                self::$variants = $concrete;
            }

            return self::$variants;
        } else {
            $key = $class . '!' . $includeSubclasses;

            if (!isset(self::$class_variants[$key])) {
                self::$class_variants[$key] = array();

                foreach (self::variants() as $variantclass => $instance) {
                    if ($instance->appliesTo($class, $includeSubclasses)) {
                        self::$class_variants[$key][$variantclass] = $instance;
                    }
                }
            }

            return self::$class_variants[$key];
        }
    }

    /**
     * Clear the cached variants
     */
    public static function clear_variant_cache()
    {
        self::$class_variants = [];
    }

    /** Holds a cache of SearchVariant_Caller instances, one for each class/includeSubclasses setting */
    protected static $call_instances = array();

    /**
     * Lets you call any function on all variants that support it, in the same manner as "Object#extend" calls
     * a method from extensions.
     *
     * Usage: SearchVariant::with(...)->call($method, $arg1, ...);
     *
     * @static
     *
     * @param string $class - (Optional) a classname. If passed, only variants that apply to that class will be checked / called
     *
     * @param bool $includeSubclasses - (Optional) If false, only variants that apply strictly to the passed class or its super-classes
     * will be checked. If true (the default), variants that apply to any sub-class of the passed class with also be checked
     *
     * @return SearchVariant_Caller An object with one method, call()
     */
    public static function with($class = null, $includeSubclasses = true)
    {
        // Make the cache key
        $key = $class ? $class . '!' . $includeSubclasses : '!';
        // If no SearchVariant_Caller instance yet, create it
        if (!isset(self::$call_instances[$key])) {
            self::$call_instances[$key] = new SearchVariant_Caller(self::variants($class, $includeSubclasses));
        }
        // Then return it
        return self::$call_instances[$key];
    }

    /**
     * Similar to {@link SearchVariant::with}, except will only use variants that apply to at least one of the classes
     * in the input array, where {@link SearchVariant::with} will run the query on the specific class you give it.
     *
     * @param string[] $classes
     * @return SearchVariant_Caller
     */
    public static function withCommon(array $classes = [])
    {
        // Allow caching
        $cacheKey = sha1(serialize($classes));
        if (isset(self::$call_instances[$cacheKey])) {
            return self::$call_instances[$cacheKey];
        }

        // Construct new array of variants applicable to at least one class in the list
        $commonVariants = [];
        foreach ($classes as $class => $options) {
            // Extract relevant class options
            $includeSubclasses = isset($options['include_children']) ? $options['include_children'] : true;

            // Get the variants for the current class
            $variantsForClass = self::variants($class, $includeSubclasses);

            // Merge the variants applicable to the current class into the list of common variants, using
            // the variant instance to replace any previous versions for the same class name (should be singleton
            // anyway).
            $commonVariants = array_replace($commonVariants, $variantsForClass);
        }

        // Cache for future calls
        self::$call_instances[$cacheKey] = new SearchVariant_Caller($commonVariants);

        return self::$call_instances[$cacheKey];
    }

    /**
     * A shortcut to with when calling without passing in a class,
     *
     * SearchVariant::call(...) ==== SearchVariant::with()->call(...);
     */

    public static function call($method, &...$args)
    {
        return self::with()->call($method, ...$args);
    }

    /**
     * Get the current state of every variant
     * @static
     * @return array
     */
    public static function current_state($class = null, $includeSubclasses = true)
    {
        $state = array();
        foreach (self::variants($class, $includeSubclasses) as $variant => $instance) {
            $state[$variant] = $instance->currentState();
        }
        return $state;
    }

    /**
     * Activate all the states in the passed argument
     * @static
     * @param array $state A set of (string)$variantClass => (any)$state pairs , e.g. as returned by
     * SearchVariant::current_state()
     * @return void
     */
    public static function activate_state($state)
    {
        foreach (self::variants() as $variant => $instance) {
            if (isset($state[$variant]) && $instance->appliesToEnvironment()) {
                $instance->activateState($state[$variant]);
            }
        }
    }

    /**
     * Return an iterator that, when used in a for loop, activates one combination of reindex states per loop, and restores
     * back to the original state at the end
     * @static
     * @param string $class - The class name to get variants for
     * @param bool $includeSubclasses - True if variants should be included if they apply to at least one subclass of $class
     * @return SearchVariant_ReindexStateIteratorRet - The iterator to foreach loop over
     */
    public static function reindex_states($class = null, $includeSubclasses = true)
    {
        $allstates = array();

        foreach (self::variants($class, $includeSubclasses) as $variant => $instance) {
            if ($states = $instance->reindexStates()) {
                $allstates[$variant] = $states;
            }
        }

        return $allstates ? new CombinationsArrayIterator($allstates) : array(array());
    }


    /**
     * Add new filter field to index safely.
     *
     * This method will respect existing filters with the same field name that
     * correspond to multiple classes
     *
     * @param SearchIndex $index
     * @param string $name Field name
     * @param array $field Field spec
     */
    protected function addFilterField($index, $name, $field)
    {
        // If field already exists, make sure to merge origin / base fields
        if (isset($index->filterFields[$name])) {
            $field['base'] = $this->mergeClasses(
                $index->filterFields[$name]['base'],
                $field['base']
            );
            $field['origin'] = $this->mergeClasses(
                $index->filterFields[$name]['origin'],
                $field['origin']
            );
        }

        $index->filterFields[$name] = $field;
    }

    /**
     * Merge sets of (or individual) class names together for a search index field.
     *
     * If there is only one unique class name, then just return it as a string instead of array.
     *
     * @param array|string $left Left class(es)
     * @param array|string $right Right class(es)
     * @return array|string List of classes, or single class
     */
    protected function mergeClasses($left, $right)
    {
        // Merge together and remove dupes
        if (!is_array($left)) {
            $left = array($left);
        }
        if (!is_array($right)) {
            $right = array($right);
        }
        $merged = array_values(array_unique(array_merge($left, $right)));

        // If there is only one item, return it as a single string
        if (count($merged) === 1) {
            return reset($merged);
        }
        return $merged;
    }
}
