<?php

namespace SilverStripe\FullTextSearch\Search\Variants;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\Versioned\Versioned;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;

class SearchVariantVersioned extends SearchVariant
{
    public function appliesTo($class, $includeSubclasses)
    {
        if (!$this->appliesToEnvironment()) {
            return false;
        }

        return SearchIntrospection::has_extension($class, Versioned::class, $includeSubclasses);
    }

    public function currentState()
    {
        return Versioned::get_stage();
    }
    public function reindexStates()
    {
        return [Versioned::DRAFT, Versioned::LIVE];
    }
    public function activateState($state)
    {
        Versioned::set_stage($state);
    }

    public function alterDefinition($class, $index)
    {
        $this->addFilterField($index, '_versionedstage', [
            'name' => '_versionedstage',
            'field' => '_versionedstage',
            'fullfield' => '_versionedstage',
            'base' => DataObject::getSchema()->baseDataClass($class),
            'origin' => $class,
            'type' => 'String',
            'lookup_chain' => [
                [
                    'call' => 'variant',
                    'variant' => get_class($this),
                    'method' => 'currentState'
                ]
            ]
        ]);
    }

    public function alterQuery($query, $index)
    {
        $query->addFilter('_versionedstage', [
            $this->currentState(),
            SearchQuery::$missing
        ]);
    }

    public function extractManipulationState(&$manipulation)
    {
        foreach ($manipulation as $table => $details) {
            $class = $details['class'];
            $stage = Versioned::DRAFT;

            if (preg_match('/^(.*)_' . Versioned::LIVE . '$/', $table, $matches)) {
                $class = DataObject::getSchema()->tableClass($matches[1]);
                $stage = Versioned::LIVE;
            }

            if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
                $manipulation[$table]['class'] = $class;
                $manipulation[$table]['state'][get_class($this)] = $stage;
            }
        }
    }

    public function extractStates(&$table, &$ids, &$fields)
    {
        $class = $table;
        $suffix = null;


        if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
            $table = $class;

            foreach ($ids as $i => $statefulid) {
                $ids[$i]['state'][get_class($this)] = $suffix ?: Versioned::DRAFT;
            }
        }
    }
}
