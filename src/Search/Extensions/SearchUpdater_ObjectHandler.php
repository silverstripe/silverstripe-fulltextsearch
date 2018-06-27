<?php

namespace SilverStripe\FullTextSearch\Search\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\FullTextSearch\Search\Updaters\SearchUpdater;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

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
        // Note: Some extensions require entire hierarchy passed to augmentWrite()
        $manipulation = array();
        foreach (ClassInfo::ancestry($this->owner) as $class) {
            if (!is_subclass_of($class, DataObject::class)) {
                continue;
            }

            $tableName = DataObject::getSchema()->tableName($class);
            $manipulation[$tableName] = array(
                'fields' => array(),
                'id' => $this->owner->ID,
                'class' => $class,
                // Note: 'delete' command not actually handled by manipulations,
                // but added so that SearchUpdater can detect the deletion
                'command' => 'delete'
            );
        }

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
        $base = DataObject::getSchema()->baseDataClass($class);
        $key = "$id:$base:" . serialize($state);

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
