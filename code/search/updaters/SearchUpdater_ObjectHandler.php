<?php

namespace SilverStripe\FullTextSearch\Search\Updaters;
use SilverStripe\ORM\DataExtension;

/**
 * Delete operations do not use database manipulations.
 *
 * If a delete has been requested, force a write on objects that should be
 * indexed.  This causes the object to be marked for deletion from the index.
 */

class SearchUpdater_ObjectHandler extends DataExtension
{
    public function onAfterDelete()
    {
        // Calling delete() on empty objects does nothing
        if (!$this->owner->ID) {
            return;
        }

        // Force SearchUpdater to mark this record as dirty
        $manipulation = array(
            $this->owner->ClassName => array(
                'fields' => array(),
                'id' => $this->owner->ID,
                'command' => 'update'
            )
        );
        $this->owner->extend('augmentWrite', $manipulation);
        SearchUpdater::handle_manipulation($manipulation);
    }

    /**
     * Forces this object to trigger a re-index in the current state
     */
    public function triggerReindex()
    {
        if (!$this->owner->ID) {
            return;
        }

        $id = $this->owner->ID;
        $class = $this->owner->ClassName;
        $state = SearchVariant::current_state($class);
        $base = ClassInfo::baseDataClass($class);
        $key = "$id:$base:".serialize($state);

        $statefulids = array(array(
            'id' => $id,
            'state' => $state
        ));

        $writes = array(
            $key => array(
                'base' => $base,
                'class' => $class,
                'id' => $id,
                'statefulids' => $statefulids,
                'fields' => array()
            )
        );

        SearchUpdater::process_writes($writes);
    }
}