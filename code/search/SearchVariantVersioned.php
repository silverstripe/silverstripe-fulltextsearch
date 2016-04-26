<?php

class SearchVariantVersioned extends SearchVariant
{
    public function appliesToEnvironment()
    {
        return class_exists('Versioned');
    }

    public function appliesTo($class, $includeSubclasses)
    {
        return SearchIntrospection::has_extension($class, 'Versioned', $includeSubclasses);
    }

    public function currentState()
    {
        return Versioned::current_stage();
    }
    public function reindexStates()
    {
        return array('Stage', 'Live');
    }
    public function activateState($state)
    {
        Versioned::reading_stage($state);
    }

    public function alterDefinition($class, $index)
    {
        $self = get_class($this);

        $this->addFilterField($index, '_versionedstage', array(
            'name' => '_versionedstage',
            'field' => '_versionedstage',
            'fullfield' => '_versionedstage',
            'base' => ClassInfo::baseDataClass($class),
            'origin' => $class,
            'type' => 'String',
            'lookup_chain' => array(array('call' => 'variant', 'variant' => $self, 'method' => 'currentState'))
        ));
    }

    public function alterQuery($query, $index)
    {
        $stage = Versioned::current_stage();
        $query->filter('_versionedstage', array($stage, SearchQuery::$missing));
    }

    public function extractManipulationState(&$manipulation)
    {
        $self = get_class($this);

        foreach ($manipulation as $table => $details) {
            $class = $details['class'];
            $stage = 'Stage';

            if (preg_match('/^(.*)_Live$/', $table, $matches)) {
                $class = $matches[1];
                $stage = 'Live';
            }

            if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
                $manipulation[$table]['class'] = $class;
                $manipulation[$table]['state'][$self] = $stage;
            }
        }
    }

    public function extractStates(&$table, &$ids, &$fields)
    {
        $class = $table;
        $suffix = null;


        if (ClassInfo::exists($class) && $this->appliesTo($class, false)) {
            $table = $class;
            $self = get_class($this);

            foreach ($ids as $i => $statefulid) {
                $ids[$i]['state'][$self] = $suffix ? $suffix : 'Stage';
            }
        }
    }
}
