<?php

namespace SilverStripe\FullTextSearch\Tests\SolrReindexTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\ORM\DataObject;

/**
 * Dummy variant that selects items with field Varient matching the current value
 *
 * Variant states are 0 and 1, or null if disabled
 */
class SolrReindexTest_Variant extends SearchVariant implements TestOnly
{
    /**
     * Value of this variant (either null, 0, or 1)
     *
     * @var int|null
     */
    protected static $current = null;

    /**
     * Activate this variant
     */
    public static function enable()
    {
        self::disable();

        self::$current = 0;
        self::$variants = array(
            self::class => singleton(self::class)
        );
    }

    /**
     * Disable this variant and reset
     */
    public static function disable()
    {
        self::$current = null;
        self::$variants = null;
        self::$class_variants = array();
        self::$call_instances = array();
    }

    public function activateState($state)
    {
        self::set_current($state);
    }

    /**
     * Set the current variant to the given state
     *
     * @param int $current 0, 1, 2, or null (disabled)
     */
    public static function set_current($current)
    {
        self::$current = $current;
    }

    /**
     * Get the current state
     *
     * @return string|null
     */
    public static function get_current()
    {
        // Always use string values for states for consistent json_encode value
        if (isset(self::$current)) {
            return (string)self::$current;
        }
    }

    public function alterDefinition($class, $index)
    {
        $self = get_class($this);

        $this->addFilterField($index, '_testvariant', array(
            'name' => '_testvariant',
            'field' => '_testvariant',
            'fullfield' => '_testvariant',
            'base' => DataObject::getSchema()->baseDataClass($class),
            'origin' => $class,
            'type' => 'Int',
            'lookup_chain' => array(array('call' => 'variant', 'variant' => $self, 'method' => 'currentState'))
        ));
    }

    public function alterQuery($query, $index)
    {
        // I guess just calling it _testvariant is ok?
        $query->filter('_testvariant', $this->currentState());
    }

    public function appliesTo($class, $includeSubclasses)
    {
        return $class === SolrReindexTest_Item::class ||
            ($includeSubclasses && is_subclass_of($class, SolrReindexTest_Item::class, true));
    }

    public function appliesToEnvironment()
    {
        // Set to null to disable
        return self::$current !== null;
    }

    public function currentState()
    {
        return self::get_current();
    }

    public function reindexStates()
    {
        // Always use string values for states for consistent json_encode value
        return array('0', '1', '2');
    }
}
