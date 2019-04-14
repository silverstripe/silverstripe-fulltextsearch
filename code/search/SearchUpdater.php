<?php

/**
 * This class is responsible for capturing changes to DataObjects and triggering index updates of the resulting dirty index
 * items.
 *
 * Attached automatically by _config calling SearchUpdater#bind_manipulation_capture. Overloads the current database connector's
 * manipulate method - basically we need to capture a manipulation _after_ all the augmentManipulation code (for instance Version's)
 * is run
 *
 * Pretty closely tied to the field structure of SearchIndex.
 *
 * TODO: The way we bind in is awful hacky.
 */
class SearchUpdater extends SS_Object
{
    /**
     * Replace the database object with a subclass that captures all manipulations and passes them to us
     */
    public static function bind_manipulation_capture()
    {
        global $databaseConfig;

        $current = DB::getConn();
        if (!$current || !$current->currentDatabase() || @$current->isManipulationCapture) {
            return;
        } // If not yet set, or its already captured, just return

        $type = get_class($current);
        $file = TEMP_FOLDER."/.cache.SMC.$type";

        if (!is_file($file)) {
            file_put_contents($file, "<?php
				class SearchManipulateCapture_$type extends $type {
					public \$isManipulationCapture = true;

					function manipulate(\$manipulation) {
						\$res = parent::manipulate(\$manipulation);
						SearchUpdater::handle_manipulation(\$manipulation);
						return \$res;
					}
				}
			");
        }

        require_once($file);
        $dbClass = 'SearchManipulateCapture_'.$type;

        /** @var SS_Database $captured */
        $captured = new $dbClass($databaseConfig);

        // Framework 3.2+ ORM needs some dependencies set
        if (method_exists($captured, "setConnector")) {
            $captured->setConnector($current->getConnector());
            $captured->setQueryBuilder($current->getQueryBuilder());
            $captured->setSchemaManager($current->getSchemaManager());
        }

        // The connection might have had it's name changed (like if we're currently in a test)
        $captured->selectDatabase($current->currentDatabase());
        DB::setConn($captured);
    }

    public static $registered = false;
    /** @var SearchUpdateProcessor */
    public static $processor = null;

    /**
     * Called by the SearchManiplateCapture database adapter with every manipulation made against the database.
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
        // First, extract any state that is in the manipulation itself
        foreach ($manipulation as $table => $details) {
            $manipulation[$table]['class'] = $table;
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

            $base = ClassInfo::baseDataClass($class);
            $key = "$id:$base:".serialize($state);

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
            }
            // Otherwise update the class label if it's more specific than the currently recorded one
            elseif (is_subclass_of($class, $writes[$key]['class'])) {
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
                                self::$processor = Injector::inst()->create('SearchUpdateProcessor');
                            }
                            self::$processor->addDirtyIDs($dirtyclass, $ids, $index);
                        }
                    }
                }
            }
        }

        // If we do have some work to do register the shutdown function to actually do the work

        // Don't do it if we're testing - there's no database connection outside the test methods, so we'd
        // just get errors
        $runningTests = class_exists('SapphireTest', false) && SapphireTest::is_running_test();

        if (self::$processor && !self::$registered && !$runningTests) {
            register_shutdown_function(array("SearchUpdater", "flush_dirty_indexes"));
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
