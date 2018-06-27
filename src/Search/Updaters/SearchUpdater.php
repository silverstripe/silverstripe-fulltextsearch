<?php

namespace SilverStripe\FullTextSearch\Search\Updaters;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Connect\Database;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\FullTextSearch\Search\FullTextSearch;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Search\Processors\SearchUpdateImmediateProcessor;
use ReflectionClass;

/**
 * This class is responsible for capturing changes to DataObjects and triggering index updates of the resulting dirty
 * index items.
 *
 * Attached automatically by Injector configuration that overloads your flavour of Database class. The
 * SearchManipulateCapture_[type] classes overload the manipulate method - basically we need to capture a
 * manipulation _after_ all the augmentManipulation code (for instance Version's) is run
 *
 * Pretty closely tied to the field structure of SearchIndex.
 */

class SearchUpdater
{
    use Configurable;

    /**
     * Whether to register the shutdown function to flush. Can be disabled for example in unit testing.
     *
     * @config
     * @var bool
     */
    private static $flush_on_shutdown = true;

    /**
     * Whether the updater is enabled. Set to false for local development if you don't have a Solr server.
     *
     * @config
     * @var bool
     */
    private static $enabled = true;

    public static $registered = false;
    /** @var SearchUpdateProcessor */
    public static $processor = null;

    /**
     * Called by the ProxyDBExtension database connector with every manipulation made against the database.
     *
     * Check every index to see what objects need re-inserting into what indexes to keep the index fresh,
     * but doesn't actually do it yet.
     *
     * TODO: This is pretty sensitive to the format of manipulation that DataObject::write produces. Specifically,
     * it expects the actual class of the object to be present as a table, regardless of if any fields changed in that table
     * (so a class => array( 'fields' => array() ) item), in order to find the actual class for a set of table manipulations
     */
    public static function handle_manipulation($manipulation)
    {
        if (!static::config()->get('enabled')) {
            return;
        }

        // First, extract any state that is in the manipulation itself
        foreach ($manipulation as $table => $details) {
            if (!isset($manipulation[$table]['class'])) {
                $manipulation[$table]['class'] = DataObject::getSchema()->tableClass($table);
            }
            $manipulation[$table]['state'] = array();
        }

        SearchVariant::call('extractManipulationState', $manipulation);

        // Then combine the manipulation back into object field sets

        $writes = array();

        foreach ($manipulation as $table => $details) {
            if (!isset($details['id'])) {
                continue;
            }

            $id = $details['id'];
            $state = $details['state'];
            $class = $details['class'];
            $command = $details['command'];
            $fields = isset($details['fields']) ? $details['fields'] : array();

            $base = DataObject::getSchema()->baseDataClass($class);
            $key = "$id:$base:" . serialize($state);

            $statefulids = array(array('id' => $id, 'state' => $state));

            // Is this the first table for this particular object? Then add an item to $writes
            if (!isset($writes[$key])) {
                $writes[$key] = array(
                    'base' => $base,
                    'class' => $class,
                    'id' => $id,
                    'statefulids' => $statefulids,
                    'command' => $command,
                    'fields' => array()
                );
            } elseif (is_subclass_of($class, $writes[$key]['class'])) {
                // Otherwise update the class label if it's more specific than the currently recorded one
                $writes[$key]['class'] = $class;
            }

            // Update the fields
            foreach ($fields as $field => $value) {
                $writes[$key]['fields']["$class:$field"] = $value;
            }
        }

        // Trim non-delete records without fields
        foreach (array_keys($writes) as $key) {
            if ($writes[$key]['command'] !== 'delete' && empty($writes[$key]['fields'])) {
                unset($writes[$key]);
            }
        }

        // Then extract any state that is needed for the writes

        SearchVariant::call('extractManipulationWriteState', $writes);

        // Submit all of these writes to the search processor

        static::process_writes($writes);
    }

    /**
     * Send updates to the current search processor for execution
     *
     * @param array $writes
     */
    public static function process_writes($writes)
    {
        foreach ($writes as $write) {
            // For every index
            foreach (FullTextSearch::get_indexes() as $index => $instance) {
                // If that index as a field from this class
                if (SearchIntrospection::is_subclass_of($write['class'], $instance->dependancyList)) {
                    // Get the dirty IDs
                    $dirtyids = $instance->getDirtyIDs($write['class'], $write['id'], $write['statefulids'], $write['fields']);

                    // Then add then then to the global list to deal with later
                    foreach ($dirtyids as $dirtyclass => $ids) {
                        if ($ids) {
                            if (!self::$processor) {
                                self::$processor = Injector::inst()->create(SearchUpdateImmediateProcessor::class);
                            }
                            self::$processor->addDirtyIDs($dirtyclass, $ids, $index);
                        }
                    }
                }
            }
        }

        // If we do have some work to do register the shutdown function to actually do the work
        if (self::$processor && !self::$registered && self::config()->get('flush_on_shutdown')) {
            register_shutdown_function(array(SearchUpdater::class, "flush_dirty_indexes"));
            self::$registered = true;
        }
    }

    /**
     * Throw away the recorded dirty IDs without doing anything with them.
     */
    public static function clear_dirty_indexes()
    {
        self::$processor = null;
    }

    /**
     * Do something with the recorded dirty IDs, where that "something" depends on the value of self::$update_method,
     * either immediately update the indexes, queue a messsage to update the indexes at some point in the future, or
     * just throw the dirty IDs away.
     */
    public static function flush_dirty_indexes()
    {
        if (!self::$processor) {
            return;
        }
        self::$processor->triggerProcessing();
        self::$processor = null;
    }
}
