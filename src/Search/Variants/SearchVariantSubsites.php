<?php

namespace SilverStripe\FullTextSearch\Search\Variants;

use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Extensions\SiteTreeSubsites;
use SilverStripe\Subsites\Extensions\GroupSubsites;
use SilverStripe\Subsites\Extensions\FileSubsites;
use SilverStripe\Subsites\Extensions\SiteConfigSubsites;
use SilverStripe\Subsites\State\SubsiteState;

if (!class_exists(Subsite::class)) {
    return;
}

class SearchVariantSubsites extends SearchVariant
{
    public function appliesToEnvironment()
    {
        return class_exists(Subsite::class) && parent::appliesToEnvironment();
    }

    public function appliesTo($class, $includeSubclasses)
    {
        if (!$this->appliesToEnvironment()) {
            return false;
        }

        // Include all DataExtensions that contain a SubsiteID.
        // TODO: refactor subsites to inherit a common interface, so we can run introspection once only.
        return SearchIntrospection::has_extension($class, SiteTreeSubsites::class, $includeSubclasses)
            || SearchIntrospection::has_extension($class, GroupSubsites::class, $includeSubclasses)
            || SearchIntrospection::has_extension($class, FileSubsites::class, $includeSubclasses)
            || SearchIntrospection::has_extension($class, SiteConfigSubsites::class, $includeSubclasses);
    }

    public function currentState()
    {
        return (string) SubsiteState::singleton()->getSubsiteId();
    }

    public function reindexStates()
    {
        static $ids = null;

        if ($ids === null) {
            $ids = ['0'];
            foreach (Subsite::get() as $subsite) {
                $ids[] = (string) $subsite->ID;
            }
        }

        return $ids;
    }

    public function activateState($state)
    {
        if (!$this->appliesToEnvironment()) {
            return;
        }

        // Note: Setting directly to the SubsiteState because we don't want the subsite ID to be persisted
        // like Subsite::changeSubsite would do.
        SubsiteState::singleton()->setSubsiteId($state);
        Permission::reset();
    }

    public function alterDefinition($class, $index)
    {
        $self = get_class($this);

        if (!$this->appliesTo($class, true)) {
            return;
        }

        // Add field to root
        $this->addFilterField($index, '_subsite', [
            'name' => '_subsite',
            'field' => '_subsite',
            'fullfield' => '_subsite',
            'base' => DataObject::getSchema()->baseDataClass($class),
            'origin' => $class,
            'type' => 'Int',
            'lookup_chain' => [['call' => 'variant', 'variant' => $self, 'method' => 'currentState']],
        ]);
    }

    /**
     * This field has been altered to allow a user to obtain search results for a particular subsite
     * When attempting to do this in project code, SearchVariantSubsites kicks and overwrites any filter you've applied
     * This fix prevents the module from doing this if a filter is applied on the index or the query, or if a field is
     * being excluded specifically before being executed.
     *
     * A pull request has been raised for this issue. Once accepted this forked module can be deleted and the parent
     * project should be used instead.
     *
     * @param SearchQuery $query
     * @param SearchIndex $index
     */
    public function alterQuery($query, $index)
    {
        if ($this->isFieldFiltered('_subsite', $query) || !$this->appliesToEnvironment()) {
            return;
        }

        $subsite = $this->currentState();
        $query->addFilter('_subsite', [$subsite, SearchQuery::$missing]);
    }

    /**
     * We need _really_ complicated logic to find just the changed subsites (because we use versions there's no explicit
     * deletes, just new versions with different members) so just always use all of them
     */
    public function extractManipulationWriteState(&$writes)
    {
        $self = get_class($this);
        $tableName = DataObject::getSchema()->tableName(Subsite::class);
        $query = SQLSelect::create('"ID"', '"' . $tableName . '"');
        $subsites = array_merge(['0'], $query->execute()->column());

        foreach ($writes as $key => $write) {
            $applies = $this->appliesTo($write['class'], true);
            if (!$applies) {
                continue;
            }

            if (isset($write['fields'][SiteTree::class . ':SubsiteID'])) {
                $subsitesForWrite = [$write['fields'][SiteTree::class . ':SubsiteID']];
            } elseif (isset($write['fields'][File::class . ':SubsiteID'])
                && (int) $write['fields'][File::class . ':SubsiteID'] !== 0
            ) {
                // files in subsite 0 should be in all subsites as they are global
                $subsitesForWrite = [$write['fields'][File::class . ':SubsiteID']];
            } else {
                $subsitesForWrite = $subsites;
            }

            $next = [];
            foreach ($write['statefulids'] as $i => $statefulid) {
                foreach ($subsitesForWrite as $subsiteID) {
                    $next[] = [
                        'id' => $statefulid['id'],
                        'state' => array_merge(
                            $statefulid['state'],
                            [$self => (string) $subsiteID]
                        ),
                    ];
                }
            }
            $writes[$key]['statefulids'] = $next;
        }
    }

    /**
     * Determine if a field with a certain name is filtered by the search query or on the index
     * This is the equivalent of saying "show me the results that do ONLY contain this value"
     * @param $field string name of the field being filtered
     * @param $query SearchQuery currently being executed
     * @param $index SearchIndex which specifies a filter field
     * @return bool true if $field is being filtered, false if it is not being filtered
     */
    protected function isFieldFiltered($field, $query)
    {
        $queryHasFilter = !empty($query->require[$field]);

        return $queryHasFilter;
    }
}
