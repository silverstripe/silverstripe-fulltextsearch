<?php

class SearchVariantSubsites extends SearchVariant
{
    public function appliesToEnvironment()
    {
        return class_exists('Subsite');
    }

    public function appliesTo($class, $includeSubclasses)
    {
        // Include all DataExtensions that contain a SubsiteID.
        // TODO: refactor subsites to inherit a common interface, so we can run introspection once only.
        return SearchIntrospection::has_extension($class, 'SiteTreeSubsites', $includeSubclasses) ||
            SearchIntrospection::has_extension($class, 'GroupSubsites', $includeSubclasses) ||
            SearchIntrospection::has_extension($class, 'FileSubsites', $includeSubclasses) ||
            SearchIntrospection::has_extension($class, 'SiteConfigSubsites', $includeSubclasses);
    }

    public function currentState()
    {
        return (string)Subsite::currentSubsiteID();
    }

    public function reindexStates()
    {
        static $ids = null;

        if ($ids === null) {
            $ids = array('0');
            foreach (DataObject::get('Subsite') as $subsite) {
                $ids[] = (string)$subsite->ID;
            }
        }

        return $ids;
    }

    public function activateState($state)
    {
        // We always just set the $_GET variable rather than store in Session - this always works, has highest priority
        // in Subsite::currentSubsiteID() and doesn't persist unlike Subsite::changeSubsite
        $_GET['SubsiteID'] = $state;
        Permission::flush_permission_cache();
    }

    public function alterDefinition($class, $index)
    {
        $self = get_class($this);

        // Add field to root
        $this->addFilterField($index, '_subsite', array(
            'name' => '_subsite',
            'field' => '_subsite',
            'fullfield' => '_subsite',
            'base' => ClassInfo::baseDataClass($class),
            'origin' => $class,
            'type' => 'Int',
            'lookup_chain' => array(array('call' => 'variant', 'variant' => $self, 'method' => 'currentState'))
        ));
    }


    public function alterQuery($query, $index)
    {
        $subsite = Subsite::currentSubsiteID();
        $query->filter('_subsite', array($subsite, SearchQuery::$missing));
    }

    /**
     * We need _really_ complicated logic to find just the changed subsites (because we use versions there's no explicit
     * deletes, just new versions with different members) so just always use all of them
     */
    public function extractManipulationWriteState(&$writes)
    {
        $self = get_class($this);
        $query = new SQLQuery('"ID"', '"Subsite"');
        $subsites = array_merge(array('0'), $query->execute()->column());

        foreach ($writes as $key => $write) {
            $applies = $this->appliesTo($write['class'], true);
            if (!$applies) {
                continue;
            }

            if (isset($write['fields']['SiteTree:SubsiteID'])) {
                $subsitesForWrite = array($write['fields']['SiteTree:SubsiteID']);
            }
            // files in subsite 0 should be in all subsites as they are global
            elseif (isset($write['fields']['File:SubsiteID']) && intval($write['fields']['File:SubsiteID']) !== 0) {
                $subsitesForWrite = array($write['fields']['File:SubsiteID']);
            }
            else {
                $subsitesForWrite = $subsites;
            }

            $next = array();
            foreach ($write['statefulids'] as $i => $statefulid) {
                foreach ($subsitesForWrite as $subsiteID) {
                    $next[] = array(
                        'id' => $statefulid['id'],
                        'state' => array_merge(
                            $statefulid['state'],
                            array($self => (string)$subsiteID)
                        )
                    );
                }
            }
            $writes[$key]['statefulids'] = $next;
        }
    }
}
