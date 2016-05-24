<?php

/**
 * SearchIndex is the base index class. Each connector will provide a subclass of this that
 * provides search engine specific behavior.
 *
 * This class is responsible for:
 *
 * - Taking index calls adding classes and fields, and resolving those to value sources and types
 *
 * - Determining which records in this index need updating when a DataObject is changed
 *
 * - Providing utilities to the connector indexes
 *
 * The connector indexes are responsible for
 *
 * - Mapping types to index configuration
 *
 * - Adding and removing items to index
 *
 * - Parsing and converting SearchQueries into a form the engine will understand, and executing those queries
 *
 * The user indexes are responsible for
 *
 * - Specifying which classes and fields this index contains
 *
 * - Specifying update rules that are not extractable from metadata (because the values come from functions for instance)
 *
 */
abstract class SearchIndex extends ViewableData
{
    /**
     * Allows this index to hide a parent index. Specifies the name of a parent index to disable
     *
     * @var string
     * @config
     */
    private static $hide_ancestor;

    public function __construct()
    {
        parent::__construct();
        $this->init();

        foreach ($this->getClasses() as $class => $options) {
            SearchVariant::with($class, $options['include_children'])->call('alterDefinition', $class, $this);
        }

        $this->buildDependancyList();
    }

    public function __toString()
    {
        return 'Search Index ' . get_class($this);
    }

    /**
     * Examines the classes this index is built on to try and find defined fields in the class hierarchy for those classes.
     * Looks for db and viewable-data fields, although can't nessecarily find type for viewable-data fields.
     */
    public function fieldData($field, $forceType = null, $extraOptions = array())
    {
        $fullfield = str_replace(".", "_", $field);
        $sources = $this->getClasses();

        foreach ($sources as $source => $options) {
            $sources[$source]['base'] = ClassInfo::baseDataClass($source);
            $sources[$source]['lookup_chain'] = array();
        }

        $found = array();

        if (strpos($field, '.') !== false) {
            $lookups = explode(".", $field);
            $field = array_pop($lookups);

            foreach ($lookups as $lookup) {
                $next = array();

                foreach ($sources as $source => $options) {
                    $class = null;

                    foreach (SearchIntrospection::hierarchy($source, $options['include_children']) as $dataclass) {
                        $singleton = singleton($dataclass);

                        if ($hasOne = $singleton->has_one($lookup)) {
                            $class = $hasOne;
                            $options['lookup_chain'][] = array(
                                'call' => 'method', 'method' => $lookup,
                                'through' => 'has_one', 'class' => $dataclass, 'otherclass' => $class, 'foreignkey' => "{$lookup}ID"
                            );
                        } elseif ($hasMany = $singleton->has_many($lookup)) {
                            $class = $hasMany;
                            $options['multi_valued'] = true;
                            $options['lookup_chain'][] = array(
                                'call' => 'method', 'method' => $lookup,
                                'through' => 'has_many', 'class' => $dataclass, 'otherclass' => $class, 'foreignkey' => $singleton->getRemoteJoinField($lookup, 'has_many')
                            );
                        } elseif ($manyMany = $singleton->many_many($lookup)) {
                            $class = $manyMany[1];
                            $options['multi_valued'] = true;
                            $options['lookup_chain'][] = array(
                                'call' => 'method', 'method' => $lookup,
                                'through' => 'many_many', 'class' => $dataclass, 'otherclass' => $class, 'details' => $manyMany
                            );
                        }

                        if ($class) {
                            if (!isset($options['origin'])) {
                                $options['origin'] = $dataclass;
                            }
                            $next[$class] = $options;
                            continue 2;
                        }
                    }
                }

                if (!$next) {
                    return $next;
                } // Early out to avoid excessive empty looping
                $sources = $next;
            }
        }

        foreach ($sources as $class => $options) {
            $dataclasses = SearchIntrospection::hierarchy($class, $options['include_children']);

            while (count($dataclasses)) {
                $dataclass = array_shift($dataclasses);
                $type = null;
                $fieldoptions = $options;

                $fields = DataObject::database_fields($dataclass);

                if (isset($fields[$field])) {
                    $type = $fields[$field];
                    $fieldoptions['lookup_chain'][] = array('call' => 'property', 'property' => $field);
                } else {
                    $singleton = singleton($dataclass);

                    if ($singleton->hasMethod("get$field") || $singleton->hasField($field)) {
                        $type = $singleton->castingClass($field);
                        if (!$type) {
                            $type = 'String';
                        }

                        if ($singleton->hasMethod("get$field")) {
                            $fieldoptions['lookup_chain'][] = array('call' => 'method', 'method' => "get$field");
                        } else {
                            $fieldoptions['lookup_chain'][] = array('call' => 'property', 'property' => $field);
                        }
                    }
                }

                if ($type) {
                    // Don't search through child classes of a class we matched on. TODO: Should we?
                    $dataclasses = array_diff($dataclasses, array_values(ClassInfo::subclassesFor($dataclass)));
                    // Trim arguments off the type string
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }
                    // Get the origin
                    $origin = isset($fieldoptions['origin']) ? $fieldoptions['origin'] : $dataclass;

                    $found["{$origin}_{$fullfield}"] = array(
                        'name' => "{$origin}_{$fullfield}",
                        'field' => $field,
                        'fullfield' => $fullfield,
                        'base' => $fieldoptions['base'],
                        'origin' => $origin,
                        'class' => $dataclass,
                        'lookup_chain' => $fieldoptions['lookup_chain'],
                        'type' => $forceType ? $forceType : $type,
                        'multi_valued' => isset($fieldoptions['multi_valued']) ? true : false,
                        'extra_options' => $extraOptions
                    );
                }
            }
        }

        return $found;
    }

    /** Public, but should only be altered by variants */

    protected $classes = array();

    protected $fulltextFields = array();

    public $filterFields = array();

    protected $sortFields = array();

    protected $excludedVariantStates = array();

    /**
     * Add a DataObject subclass whose instances should be included in this index
     *
     * Can only be called when addFulltextField, addFilterField, addSortField and addAllFulltextFields have not
     * yet been called for this index instance
     *
     * @throws Exception
     * @param String $class - The class to include
     * @param array $options - TODO: Remove
     */
    public function addClass($class, $options = array())
    {
        if ($this->fulltextFields || $this->filterFields || $this->sortFields) {
            throw new Exception('Can\'t add class to Index after fields have already been added');
        }

        if (!DataObject::has_own_table($class)) {
            throw new InvalidArgumentException('Can\'t add classes which don\'t have data tables (no $db or $has_one set on the class)');
        }

        $options = array_merge(array(
            'include_children' => true
        ), $options);

        $this->classes[$class] = $options;
    }

    /**
     * Get the classes added by addClass
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Add a field that should be fulltext searchable
     * @param String $field - The field to add
     * @param String $forceType - The type to force this field as (required in some cases, when not detectable from metadata)
     * @param String $extraOptions - Dependent on search implementation
     */
    public function addFulltextField($field, $forceType = null, $extraOptions = array())
    {
        $this->fulltextFields = array_merge($this->fulltextFields, $this->fieldData($field, $forceType, $extraOptions));
    }

    public function getFulltextFields()
    {
        return $this->fulltextFields;
    }

    /**
     * Add a field that should be filterable
     * @param String $field - The field to add
     * @param String $forceType - The type to force this field as (required in some cases, when not detectable from metadata)
     * @param String $extraOptions - Dependent on search implementation
     */
    public function addFilterField($field, $forceType = null, $extraOptions = array())
    {
        $this->filterFields = array_merge($this->filterFields, $this->fieldData($field, $forceType, $extraOptions));
    }

    public function getFilterFields()
    {
        return $this->filterFields;
    }

    /**
     * Add a field that should be sortable
     * @param String $field - The field to add
     * @param String $forceType - The type to force this field as (required in some cases, when not detectable from metadata)
     * @param String $extraOptions - Dependent on search implementation
     */
    public function addSortField($field, $forceType = null, $extraOptions = array())
    {
        $this->sortFields = array_merge($this->sortFields, $this->fieldData($field, $forceType, $extraOptions));
    }

    public function getSortFields()
    {
        return $this->sortFields;
    }

    /**
     * Add all database-backed text fields as fulltext searchable fields.
     *
     * For every class included in the index, examines those classes and all subclasses looking for "Text" database
     * fields (Varchar, Text, HTMLText, etc) and adds them all as fulltext searchable fields.
     */
    public function addAllFulltextFields($includeSubclasses = true)
    {
        foreach ($this->getClasses() as $class => $options) {
            foreach (SearchIntrospection::hierarchy($class, $includeSubclasses, true) as $dataclass) {
                $fields = DataObject::database_fields($dataclass);

                foreach ($fields as $field => $type) {
                    if (preg_match('/^(\w+)\(/', $type, $match)) {
                        $type = $match[1];
                    }
                    list($type, $args) = Object::parse_class_spec($type);
                    if (is_subclass_of($type, 'StringField')) {
                        $this->addFulltextField($field);
                    }
                }
            }
        }
    }

    /**
     * Returns an interator that will let you interate through all added fields, regardless of whether they
     * were added as fulltext, filter or sort fields.
     *
     * @return MultipleArrayIterator
     */
    public function getFieldsIterator()
    {
        return new MultipleArrayIterator($this->fulltextFields, $this->filterFields, $this->sortFields);
    }

    public function excludeVariantState($state)
    {
        $this->excludedVariantStates[] = $state;
    }

    /** Returns true if some variant state should be ignored */
    public function variantStateExcluded($state)
    {
        foreach ($this->excludedVariantStates as $excludedstate) {
            $matches = true;

            foreach ($excludedstate as $variant => $variantstate) {
                if (!isset($state[$variant]) || $state[$variant] != $variantstate) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }
    }

    public $dependancyList = array();

    public function buildDependancyList()
    {
        $this->dependancyList = array_keys($this->getClasses());

        foreach ($this->getFieldsIterator() as $name => $field) {
            if (!isset($field['class'])) {
                continue;
            }
            SearchIntrospection::add_unique_by_ancestor($this->dependancyList, $field['class']);
        }
    }

    public $derivedFields = null;

    /**
     * Returns an array where each member is all the fields and the classes that are at the end of some
     * specific lookup chain from one of the base classes
     */
    public function getDerivedFields()
    {
        if ($this->derivedFields === null) {
            $this->derivedFields = array();

            foreach ($this->getFieldsIterator() as $name => $field) {
                if (count($field['lookup_chain']) < 2) {
                    continue;
                }

                $key = sha1($field['base'].serialize($field['lookup_chain']));
                $fieldname = "{$field['class']}:{$field['field']}";

                if (isset($this->derivedFields[$key])) {
                    $this->derivedFields[$key]['fields'][$fieldname] = $fieldname;
                    SearchIntrospection::add_unique_by_ancestor($this->derivedFields['classes'], $field['class']);
                } else {
                    $chain = array_reverse($field['lookup_chain']);
                    array_shift($chain);

                    $this->derivedFields[$key] = array(
                        'base' => $field['base'],
                        'fields' => array($fieldname => $fieldname),
                        'classes' => array($field['class']),
                        'chain' => $chain
                    );
                }
            }
        }

        return $this->derivedFields;
    }

    /**
     * Get the "document ID" (a database & variant unique id) given some "Base" class, DataObject ID and state array
     *
     * @param String $base - The base class of the object
     * @param Integer $id - The ID of the object
     * @param Array $state - The variant state of the object
     * @return string - The document ID as a string
     */
    public function getDocumentIDForState($base, $id, $state)
    {
        ksort($state);
        $parts = array('id' => $id, 'base' => $base, 'state' => json_encode($state));
        return implode('-', array_values($parts));
    }

    /**
     * Get the "document ID" (a database & variant unique id) given some "Base" class and DataObject
     *
     * @param DataObject $object - The object
     * @param String $base - The base class of the object
     * @param Boolean $includesubs - TODO: Probably going away
     * @return string - The document ID as a string
     */
    public function getDocumentID($object, $base, $includesubs)
    {
        return $this->getDocumentIDForState($base, $object->ID, SearchVariant::current_state($base, $includesubs));
    }

    /**
     * Given an object and a field definition (as returned by fieldData) get the current value of that field on that object
     *
     * @param DataObject $object - The object to get the value from
     * @param Array $field - The field definition to use
     * @return Mixed - The value of the field, or null if we couldn't look it up for some reason
     */
    protected function _getFieldValue($object, $field)
    {
        set_error_handler(create_function('$no, $str', 'throw new Exception("HTML Parse Error: ".$str);'), E_ALL);

        try {
            foreach ($field['lookup_chain'] as $step) {
                // Just fail if we've fallen off the end of the chain
                if (!$object) {
                    return null;
                }

                // If we're looking up this step on an array or SS_List, do the step on every item, merge result
                if (is_array($object) || $object instanceof SS_List) {
                    $next = array();

                    foreach ($object as $item) {
                        if ($step['call'] == 'method') {
                            $method = $step['method'];
                            $item = $item->$method();
                        } else {
                            $property = $step['property'];
                            $item = $item->$property;
                        }

                        if ($item instanceof SS_List) {
                            $next = array_merge($next, $item->toArray());
                        } elseif (is_array($item)) {
                            $next = array_merge($next, $item);
                        } else {
                            $next[] = $item;
                        }
                    }

                    $object = $next;
                }
                // Otherwise, just call
                else {
                    if ($step['call'] == 'method') {
                        $method = $step['method'];
                        $object = $object->$method();
                    } elseif ($step['call'] == 'variant') {
                        $variants = SearchVariant::variants();
                        $variant = $variants[$step['variant']];
                        $method = $step['method'];
                        $object = $variant->$method($object);
                    } else {
                        $property = $step['property'];
                        $object = $object->$property;
                    }
                }
            }
        } catch (Exception $e) {
            static::warn($e);
            $object = null;
        }

        restore_error_handler();
        return $object;
    }

    /**
     * Log non-fatal errors
     *
     * @param Exception $e
     * @throws Exception
     */
    public static function warn($e) {
        // Noisy errors during testing
        if(class_exists('SapphireTest', false) && SapphireTest::is_running_test()) {
            throw $e;
        }
        SS_Log::log($e, SS_Log::WARN);
    }

    /**
     * Given a class, object id, set of stateful ids and a list of changed fields (in a special format),
     * return what statefulids need updating in this index
     *
     * Internal function used by SearchUpdater.
     *
     * @param  $class
     * @param  $id
     * @param  $statefulids
     * @param  $fields
     * @return array
     */
    public function getDirtyIDs($class, $id, $statefulids, $fields)
    {
        $dirty = array();

        // First, if this object is directly contained in the index, add it
        foreach ($this->classes as $searchclass => $options) {
            if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) {
                $base = ClassInfo::baseDataClass($searchclass);
                $dirty[$base] = array();
                foreach ($statefulids as $statefulid) {
                    $key = serialize($statefulid);
                    $dirty[$base][$key] = $statefulid;
                }
            }
        }

        $current = SearchVariant::current_state();


        // Then, for every derived field
        foreach ($this->getDerivedFields() as $derivation) {
            // If the this object is a subclass of any of the classes we want a field from
            if (!SearchIntrospection::is_subclass_of($class, $derivation['classes'])) {
                continue;
            }
            if (!array_intersect_key($fields, $derivation['fields'])) {
                continue;
            }

            foreach (SearchVariant::reindex_states($class, false) as $state) {
                SearchVariant::activate_state($state);

                $ids = array($id);

                foreach ($derivation['chain'] as $step) {
                    if ($step['through'] == 'has_one') {
                        $sql = new SQLQuery('"ID"', '"'.$step['class'].'"', '"'.$step['foreignkey'].'" IN ('.implode(',', $ids).')');
                        singleton($step['class'])->extend('augmentSQL', $sql);

                        $ids = $sql->execute()->column();
                    } elseif ($step['through'] == 'has_many') {
                        $sql = new SQLQuery('"'.$step['class'].'"."ID"', '"'.$step['class'].'"', '"'.$step['otherclass'].'"."ID" IN ('.implode(',', $ids).')');
                        $sql->addInnerJoin($step['otherclass'], '"'.$step['class'].'"."ID" = "'.$step['otherclass'].'"."'.$step['foreignkey'].'"');
                        singleton($step['class'])->extend('augmentSQL', $sql);

                        $ids = $sql->execute()->column();
                    }
                    
                    if (empty($ids)) {
                        break;
                    }
                }

                SearchVariant::activate_state($current);

                if ($ids) {
                    $base = $derivation['base'];
                    if (!isset($dirty[$base])) {
                        $dirty[$base] = array();
                    }

                    foreach ($ids as $rid) {
                        $statefulid = array('id' => $rid, 'state' => $state);
                        $key = serialize($statefulid);
                        $dirty[$base][$key] = $statefulid;
                    }
                }
            }
        }

        return $dirty;
    }

    /** !! These should be implemented by the full text search engine */

    abstract public function add($object) ;
    abstract public function delete($base, $id, $state) ;

    abstract public function commit();

    /** !! These should be implemented by the specific index */

    /**
     * Called during construction, this is the method that builds the structure.
     * Used instead of overriding __construct as we have specific execution order - code that has
     * to be run before _and/or_ after this.
     */
    abstract public function init();
}

/**
 * A search index that does nothing. Useful for testing
 */
abstract class SearchIndex_Null extends SearchIndex
{
    public function add($object)
    {
    }

    public function delete($base, $id, $state)
    {
    }

    public function commit()
    {
    }
}

/**
 * A search index that just records actions. Useful for testing
 */
abstract class SearchIndex_Recording extends SearchIndex
{
    public $added = array();
    public $deleted = array();
    public $committed = false;

    public function reset()
    {
        $this->added = array();
        $this->deleted = array();
        $this->committed = false;
    }

    public function add($object)
    {
        $res = array();

        $res['ID'] = $object->ID;

        foreach ($this->getFieldsIterator() as $name => $field) {
            $val = $this->_getFieldValue($object, $field);
            $res[$name] = $val;
        }

        $this->added[] = $res;
    }

    public function getAdded($fields = array())
    {
        $res = array();

        foreach ($this->added as $added) {
            $filtered = array();
            foreach ($fields as $field) {
                if (isset($added[$field])) {
                    $filtered[$field] = $added[$field];
                }
            }
            $res[] = $filtered;
        }

        return $res;
    }

    public function delete($base, $id, $state)
    {
        $this->deleted[] = array('base' => $base, 'id' => $id, 'state' => $state);
    }

    public function commit()
    {
        $this->committed = true;
    }

    public function getIndexName()
    {
        return get_class($this);
    }

    public function getIsCommitted()
    {
        return $this->committed;
    }

    public function getService()
    {
        // Causes commits to the service to be redirected back to the same object
        return $this;
    }
}
