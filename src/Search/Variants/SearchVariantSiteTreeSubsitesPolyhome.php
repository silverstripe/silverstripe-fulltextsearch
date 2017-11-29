<?php
namespace SilverStripe\FullTextSearch\Search\Variants;

use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;

if (!class_exists('Subsite') || !class_exists('SubsitePolyhome')) {
    return;
}

class SearchVariantSiteTreeSubsitesPolyhome extends SearchVariant
{
    public function appliesToEnvironment()
    {
        return class_exists('Subsite') && class_exists('SubsitePolyhome');
    }

    public function appliesTo($class, $includeSubclasses)
    {
        return SearchIntrospection::has_extension($class, 'SiteTreeSubsitesPolyhome', $includeSubclasses);
    }

    public function currentState()
    {
        return Subsite::currentSubsiteID();
    }
    public function reindexStates()
    {
        static $ids = null;

        if ($ids === null) {
            $ids = array(0);
            foreach (DataObject::get('Subsite') as $subsite) {
                $ids[] = $subsite->ID;
            }
        }

        return $ids;
    }
    public function activateState($state)
    {
        if (Controller::has_curr()) {
            Subsite::changeSubsite($state);
        } else {
            // TODO: This is a nasty hack - calling Subsite::changeSubsite after request ends
            // throws error because no current controller to access session on
            $_REQUEST['SubsiteID'] = $state;
        }
    }

    public function alterDefinition($class, $index)
    {
        $self = get_class($this);

        $this->addFilterField($index, '_subsite', array(
            'name' => '_subsite',
            'field' => '_subsite',
            'fullfield' => '_subsite',
            'base' => DataObject::getSchema()->baseDataClass($class),
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

    public static $subsites = null;

    /**
     * We need _really_ complicated logic to find just the changed subsites (because we use versions there's no explicit
     * deletes, just new versions with different members) so just always use all of them
     */
    public function extractManipulationWriteState(&$writes)
    {
        $self = get_class($this);

        foreach ($writes as $key => $write) {
            if (!$this->appliesTo($write['class'], true)) {
                continue;
            }

            if (self::$subsites === null) {
                $query = new SQLSelect('ID', 'Subsite');
                self::$subsites = array_merge(array('0'), $query->execute()->column());
            }

            $next = array();

            foreach ($write['statefulids'] as $i => $statefulid) {
                foreach (self::$subsites as $subsiteID) {
                    $next[] = array('id' => $statefulid['id'], 'state' => array_merge($statefulid['state'], array($self => $subsiteID)));
                }
            }

            $writes[$key]['statefulids'] = $next;
        }
    }
}
