<?php

namespace SilverStripe\FullTextSearch\Solr;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\FullTextSearch\Search\Indexes\SearchIndex;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant_Caller;
use SilverStripe\FullTextSearch\Solr\Services\SolrService;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery;
use SilverStripe\FullTextSearch\Search\Queries\SearchQuery_Range;
use SilverStripe\FullTextSearch\Search\Variants\SearchVariant;
use SilverStripe\FullTextSearch\Search\SearchIntrospection;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;

abstract class SolrIndex extends SearchIndex
{
    public static $fulltextTypeMap = array(
        '*' => 'text',
        'HTMLVarchar' => 'htmltext',
        'HTMLText' => 'htmltext'
    );

    public static $filterTypeMap = array(
        '*' => 'string',
        'Boolean' => 'boolean',
        'Date' => 'tdate',
        'SSDatetime' => 'tdate',
        'SS_Datetime' => 'tdate',
        'ForeignKey' => 'tint',
        'Int' => 'tint',
        'Float' => 'tfloat',
        'Double' => 'tdouble'
    );

    public static $sortTypeMap = array();

    protected $analyzerFields = array();

    protected $copyFields = array();

    protected $extrasPath = null;

    protected $templatesPath = null;

    private static $casting = [
        'FieldDefinitions' => 'HTMLText',
        'CopyFieldDefinitions' => 'HTMLText'
    ];

    /**
     * List of boosted fields
     *
     * @var array
     */
    protected $boostedFields = array();

    /**
     * Name of default field
     *
     * @var string
     * @config
     */
    private static $default_field = '_text';

    /**
     * List of copy fields all fulltext fields should be copied into.
     * This will fallback to default_field if not specified
     *
     * @var array
     */
    private static $copy_fields = array();

    /**
     * @return String Absolute path to the folder containing
     * templates which are used for generating the schema and field definitions.
     */
    public function getTemplatesPath()
    {
        $globalOptions = Solr::solr_options();
        $path = $this->templatesPath ? $this->templatesPath : $globalOptions['templatespath'];
        return rtrim($path, '/');
    }

    /**
     * @return String Absolute path to the configuration default files,
     * e.g. solrconfig.xml.
     */
    public function getExtrasPath()
    {
        $globalOptions = Solr::solr_options();
        return $this->extrasPath ? $this->extrasPath : $globalOptions['extraspath'];
    }

    public function generateSchema()
    {
        return $this->renderWith($this->getTemplatesPath() . '/schema.ss');
    }

    /**
     * Helper for returning the correct index name. Supports prefixing and
     * suffixing
     *
     * @return string
     */
    public function getIndexName()
    {
        $name = $this->sanitiseClassName(get_class($this), '-');

        $indexParts = [$name];

        if ($indexPrefix = Environment::getEnv('SS_SOLR_INDEX_PREFIX')) {
            array_unshift($indexParts, $indexPrefix);
        }

        if ($indexSuffix = Environment::getEnv('SS_SOLR_INDEX_SUFFIX')) {
            $indexParts[] = $indexSuffix;
        }

        return implode($indexParts);
    }

    public function getTypes()
    {
        return $this->renderWith($this->getTemplatesPath() . '/types.ss');
    }

    /**
     * Index-time analyzer which is applied to a specific field.
     * Can be used to remove HTML tags, apply stemming, etc.
     *
     * @see http://wiki.apache.org/solr/AnalyzersTokenizersTokenFilters#solr.WhitespaceTokenizerFactory
     *
     * @param string $field
     * @param string $type
     * @param array $params parameters for the analyzer, usually at least a "class"
     */
    public function addAnalyzer($field, $type, $params)
    {
        $fullFields = $this->fieldData($field);
        if ($fullFields) {
            foreach ($fullFields as $fullField => $spec) {
                if (!isset($this->analyzerFields[$fullField])) {
                    $this->analyzerFields[$fullField] = array();
                }
                $this->analyzerFields[$fullField][$type] = $params;
            }
        }
    }

    /**
     * Get the default text field, normally '_text'
     *
     * @return string
     */
    public function getDefaultField()
    {
        return $this->config()->default_field;
    }

    /**
     * Get list of fields each text field should be copied into.
     * This will fallback to the default field if omitted.
     *
     * @return array
     */
    protected function getCopyDestinations()
    {
        $copyFields = $this->config()->copy_fields;
        if ($copyFields) {
            return $copyFields;
        }
        // Fallback to default field
        $df = $this->getDefaultField();
        return array($df);
    }

    public function getFieldDefinitions()
    {
        $xml = array();
        $stored = $this->getStoredDefault();

        $xml[] = "";

        // Add the hardcoded field definitions

        $xml[] = "<field name='_documentid' type='string' indexed='true' stored='true' required='true' />";

        $xml[] = "<field name='ID' type='tint' indexed='true' stored='true' required='true' />";
        $xml[] = "<field name='ClassName' type='string' indexed='true' stored='true' required='true' />";
        $xml[] = "<field name='ClassHierarchy' type='string' indexed='true' stored='true' required='true' multiValued='true' />";

        // Add the fulltext collation field

        $df = $this->getDefaultField();
        $xml[] = "<field name='{$df}' type='htmltext' indexed='true' stored='{$stored}' multiValued='true' />" ;

        // Add the user-specified fields

        foreach ($this->fulltextFields as $name => $field) {
            $xml[] = $this->getFieldDefinition($name, $field, self::$fulltextTypeMap);
        }

        foreach ($this->filterFields as $name => $field) {
            if ($field['fullfield'] == 'ID' || $field['fullfield'] == 'ClassName') {
                continue;
            }
            $xml[] = $this->getFieldDefinition($name, $field);
        }

        foreach ($this->sortFields as $name => $field) {
            if ($field['fullfield'] == 'ID' || $field['fullfield'] == 'ClassName') {
                continue;
            }
            $xml[] = $this->getFieldDefinition($name, $field);
        }

        return implode("\n\t\t", $xml);
    }

    /**
     * Extract first suggestion text from collated values
     *
     * @param mixed $collation
     * @return string
     */
    protected function getCollatedSuggestion($collation = '')
    {
        if (is_string($collation)) {
            return $collation;
        }
        if (is_object($collation)) {
            if (isset($collation->misspellingsAndCorrections)) {
                foreach ($collation->misspellingsAndCorrections as $key => $value) {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * Extract a human friendly spelling suggestion from a Solr spellcheck collation string.
     * @param string $collation
     * @return String
     */
    protected function getNiceSuggestion($collation = '')
    {
        $collationParts = explode(' ', $collation);

        // Remove advanced query params from the beginning of each collation part.
        foreach ($collationParts as $key => &$part) {
            $part = ltrim($part, '+');
        }

        return implode(' ', $collationParts);
    }

    /**
     * Extract a query string from a Solr spellcheck collation string.
     * Useful for constructing 'Did you mean?' links, for example:
     * <a href="http://example.com/search?q=$SuggestionQueryString">$SuggestionNice</a>
     * @param string $collation
     * @return String
     */
    protected function getSuggestionQueryString($collation = '')
    {
        return str_replace(' ', '+', $this->getNiceSuggestion($collation));
    }

    /**
     * Add a field that should be stored
     *
     * @param string $field The field to add
     * @param string $forceType The type to force this field as (required in some cases, when not
     * detectable from metadata)
     * @param array $extraOptions Dependent on search implementation
     */
    public function addStoredField($field, $forceType = null, $extraOptions = array())
    {
        $options = array_merge($extraOptions, array('stored' => 'true'));
        $this->addFulltextField($field, $forceType, $options);
    }

    /**
     * Add a fulltext field with a boosted value
     *
     * @param string $field The field to add
     * @param string $forceType The type to force this field as (required in some cases, when not
     * detectable from metadata)
     * @param array $extraOptions Dependent on search implementation
     * @param float $boost Numeric boosting value (defaults to 2)
     */
    public function addBoostedField($field, $forceType = null, $extraOptions = array(), $boost = 2)
    {
        $options = array_merge($extraOptions, array('boost' => $boost));
        $this->addFulltextField($field, $forceType, $options);
    }


    public function fieldData($field, $forceType = null, $extraOptions = array())
    {
        // Ensure that 'boost' is recorded here without being captured by solr
        $boost = null;
        if (array_key_exists('boost', $extraOptions)) {
            $boost = $extraOptions['boost'];
            unset($extraOptions['boost']);
        }
        $data = parent::fieldData($field, $forceType, $extraOptions);

        // Boost all fields with this name
        if (isset($boost)) {
            foreach ($data as $fieldName => $fieldInfo) {
                $this->boostedFields[$fieldName] = $boost;
            }
        }
        return $data;
    }

    /**
     * Set the default boosting level for a specific field.
     * Will control the default value for qf param (Query Fields), but will not
     * override a query-specific value.
     *
     * Fields must be added before having a field boosting specified
     *
     * @param string $field Full field key (Model_Field)
     * @param float|null $level Numeric boosting value. Set to null to clear boost
     */
    public function setFieldBoosting($field, $level)
    {
        if (!isset($this->fulltextFields[$field])) {
            throw new \InvalidArgumentException("No fulltext field $field exists on " . $this->getIndexName());
        }
        if ($level === null) {
            unset($this->boostedFields[$field]);
        } else {
            $this->boostedFields[$field] = $level;
        }
    }

    /**
     * Get all boosted fields
     *
     * @return array
     */
    public function getBoostedFields()
    {
        return $this->boostedFields;
    }

    /**
     * Determine the best default value for the 'qf' parameter
     *
     * @return array|null List of query fields, or null if not specified
     */
    public function getQueryFields()
    {
        // Not necessary to specify this unless boosting
        if (empty($this->boostedFields)) {
            return null;
        }
        $queryFields = array();
        foreach ($this->boostedFields as $fieldName => $boost) {
            $queryFields[] = $fieldName . '^' . $boost;
        }

        // If any fields are queried, we must always include the default field, otherwise it will be excluded
        $df = $this->getDefaultField();
        if ($queryFields && !isset($this->boostedFields[$df])) {
            $queryFields[] = $df;
        }

        return $queryFields;
    }

    /**
     * Gets the default 'stored' value for fields in this index
     *
     * @return string A default value for the 'stored' field option, either 'true' or 'false'
     */
    protected function getStoredDefault()
    {
        return Director::isDev() ? 'true' : 'false';
    }

    /**
     * @param string $name
     * @param Array $spec
     * @param Array $typeMap
     * @return String XML
     */
    protected function getFieldDefinition($name, $spec, $typeMap = null)
    {
        if (!$typeMap) {
            $typeMap = self::$filterTypeMap;
        }
        $multiValued = (isset($spec['multi_valued']) && $spec['multi_valued']) ? "true" : '';
        $type = isset($typeMap[$spec['type']]) ? $typeMap[$spec['type']] : $typeMap['*'];

        $analyzerXml = '';
        if (isset($this->analyzerFields[$name])) {
            foreach ($this->analyzerFields[$name] as $analyzerType => $analyzerParams) {
                $analyzerXml .= $this->toXmlTag($analyzerType, $analyzerParams);
            }
        }

        $fieldParams = array_merge(
            array(
                'name' => $name,
                'type' => $type,
                'indexed' => 'true',
                'stored' => $this->getStoredDefault(),
                'multiValued' => $multiValued
            ),
            isset($spec['extra_options']) ? $spec['extra_options'] : array()
        );

        return $this->toXmlTag(
            "field",
            $fieldParams,
            $analyzerXml ? "<analyzer>$analyzerXml</analyzer>" : null
        );
    }

    /**
     * Convert definition to XML tag
     *
     * @param string $tag
     * @param string $attrs Map of attributes
     * @param string $content Inner content
     * @return String XML tag
     */
    protected function toXmlTag($tag, $attrs, $content = null)
    {
        $xml = "<$tag ";
        if ($attrs) {
            $attrStrs = array();
            foreach ($attrs as $attrName => $attrVal) {
                $attrStrs[] = "$attrName='$attrVal'";
            }
            $xml .= $attrStrs ? implode(' ', $attrStrs) : '';
        }
        $xml .= $content ? ">$content</$tag>" : '/>';
        return $xml;
    }

    /**
     * @param string $source Composite field name (<class>_<fieldname>)
     * @param string $dest
     */
    public function addCopyField($source, $dest, $extraOptions = array())
    {
        if (!isset($this->copyFields[$source])) {
            $this->copyFields[$source] = array();
        }
        $this->copyFields[$source][] = array_merge(
            array('source' => $source, 'dest' => $dest),
            $extraOptions
        );
    }

    /**
     * Generate XML for copy field definitions
     *
     * @return string
     */
    public function getCopyFieldDefinitions()
    {
        $xml = array();

        // Default copy fields
        foreach ($this->getCopyDestinations() as $copyTo) {
            foreach ($this->fulltextFields as $name => $field) {
                $xml[] = "<copyField source='{$name}' dest='{$copyTo}' />";
            }
        }

        // Explicit copy fields
        foreach ($this->copyFields as $source => $fields) {
            foreach ($fields as $fieldAttrs) {
                $xml[] = $this->toXmlTag('copyField', $fieldAttrs);
            }
        }

        return implode("\n\t", $xml);
    }

    /**
     * Determine if the given object is one of the given type
     *
     * @param string $class
     * @param array|string $base Class or list of base classes
     * @return bool
     */
    protected function classIs($class, $base)
    {
        if (is_array($base)) {
            foreach ($base as $nextBase) {
                if ($this->classIs($class, $nextBase)) {
                    return true;
                }
            }
            return false;
        }

        // Check single origin
        return $class === $base || is_subclass_of($class, $base);
    }

    protected function _addField($doc, $object, $field)
    {
        $class = get_class($object);
        if (!$this->classIs($class, $field['origin'])) {
            return;
        }

        $value = $this->_getFieldValue($object, $field);

        $type = isset(self::$filterTypeMap[$field['type']]) ? self::$filterTypeMap[$field['type']] : self::$filterTypeMap['*'];

        if (is_array($value)) {
            foreach ($value as $sub) {
                /* Solr requires dates in the form 1995-12-31T23:59:59Z */
                if ($type == 'tdate') {
                    if (!$sub) {
                        continue;
                    }
                    $sub = gmdate('Y-m-d\TH:i:s\Z', strtotime($sub));
                }

                /* Solr requires numbers to be valid if presented, not just empty */
                if (($type == 'tint' || $type == 'tfloat' || $type == 'tdouble') && !is_numeric($sub)) {
                    continue;
                }

                $doc->addField($field['name'], $sub);
            }
        } else {
            /* Solr requires dates in the form 1995-12-31T23:59:59Z */
            if ($type == 'tdate') {
                if (!$value) {
                    return;
                }
                $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
            }

            /* Solr requires numbers to be valid if presented, not just empty */
            if (($type == 'tint' || $type == 'tfloat' || $type == 'tdouble') && !is_numeric($value)) {
                return;
            }

            // Only index fields that are not null
            if ($value !== null) {
                $doc->setField($field['name'], $value);
            }
        }
    }

    protected function _addAs($object, $base, $options)
    {
        $includeSubs = $options['include_children'];

        $doc = new \Apache_Solr_Document();

        // Always present fields

        $doc->setField('_documentid', $this->getDocumentID($object, $base, $includeSubs));
        $doc->setField('ID', $object->ID);
        $doc->setField('ClassName', $object->ClassName);

        foreach (SearchIntrospection::hierarchy(get_class($object), false) as $class) {
            $doc->addField('ClassHierarchy', $class);
        }

        // Add the user-specified fields

        foreach ($this->getFieldsIterator() as $name => $field) {
            if ($field['base'] === $base || (is_array($field['base']) && in_array($base, $field['base']))) {
                $this->_addField($doc, $object, $field);
            }
        }

        try {
            $this->getService()->addDocument($doc);
        } catch (Exception $e) {
            static::warn($e);
            return false;
        }

        return $doc;
    }

    public function add($object)
    {
        $class = get_class($object);
        $docs = array();

        foreach ($this->getClasses() as $searchclass => $options) {
            if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) {
                $base = DataObject::getSchema()->baseDataClass($searchclass);
                $docs[] = $this->_addAs($object, $base, $options);
            }
        }

        return $docs;
    }

    public function canAdd($class)
    {
        foreach ($this->classes as $searchclass => $options) {
            if ($searchclass == $class || ($options['include_children'] && is_subclass_of($class, $searchclass))) {
                return true;
            }
        }

        return false;
    }

    public function delete($base, $id, $state)
    {
        $documentID = $this->getDocumentIDForState($base, $id, $state);

        try {
            $this->getService()->deleteById($documentID);
        } catch (Exception $e) {
            static::warn($e);
            return false;
        }

        return true;
    }

    /**
     * Clear all records which do not match the given classname whitelist.
     *
     * Can also be used to trim an index when reducing to a narrower set of classes.
     *
     * Ignores current state / variant.
     *
     * @param array $classes List of non-obsolete classes in the same format as SolrIndex::getClasses()
     * @return bool Flag if successful
     * @throws \Apache_Solr_HttpTransportException
     */
    public function clearObsoleteClasses($classes)
    {
        if (empty($classes)) {
            return false;
        }

        // Delete all records which do not match the necessary classname rules
        $conditions = array();
        foreach ($classes as $class => $options) {
            if ($options['include_children']) {
                $conditions[] = "ClassHierarchy:{$class}";
            } else {
                $conditions[] = "ClassName:{$class}";
            }
        }

        // Delete records which don't match any of these conditions in this index
        $deleteQuery = "-(" . implode(' ', $conditions) . ")";
        $this
            ->getService()
            ->deleteByQuery($deleteQuery);
        return true;
    }

    public function commit()
    {
        try {
            $this->getService()->commit(false, false, false);
        } catch (Exception $e) {
            static::warn($e);
            return false;
        }

        return true;
    }

    /**
     * @param SearchQuery $query
     * @param integer $offset
     * @param integer $limit
     * @param array $params Extra request parameters passed through to Solr
     * @return ArrayData Map with the following keys:
     *  - 'Matches': ArrayList of the matched object instances
     * @throws \Apache_Solr_HttpTransportException
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = array())
    {
        $service = $this->getService();
        $this->applySearchVariants($query);

        $q = array(); // Query
        $fq = array(); // Filter query
        $qf = array(); // Query fields
        $hlq = array(); // Highlight query

        // Build the search itself
        $q = $this->getQueryComponent($query, $hlq);

        // If using boosting, set the clean term separately for highlighting.
        // See https://issues.apache.org/jira/browse/SOLR-2632
        if (array_key_exists('hl', $params) && !array_key_exists('hl.q', $params)) {
            $params['hl.q'] = implode(' ', $hlq);
        }

        // Filter by class if requested
        $classq = array();
        foreach ($query->classes as $class) {
            if (!empty($class['includeSubclasses'])) {
                $classq[] = 'ClassHierarchy:' . $this->sanitiseClassName($class['class']);
            } else {
                $classq[] = 'ClassName:' . $this->sanitiseClassName($class['class']);
            }
        }
        if ($classq) {
            $fq[] = '+(' . implode(' ', $classq) . ')';
        }

        // Filter by filters
        $fq = array_merge($fq, $this->getFiltersComponent($query));

        // Prepare query fields unless specified explicitly
        if (isset($params['qf'])) {
            $qf = $params['qf'];
        } else {
            $qf = $this->getQueryFields();
        }
        if (is_array($qf)) {
            $qf = implode(' ', $qf);
        }
        if ($qf) {
            $params['qf'] = $qf;
        }

        if (!headers_sent() && Director::isDev()) {
            if ($q) {
                header('X-Query: ' . implode(' ', $q));
            }
            if ($fq) {
                header('X-Filters: "' . implode('", "', $fq) . '"');
            }
            if ($qf) {
                header('X-QueryFields: ' . $qf);
            }
        }

        if ($offset == -1) {
            $offset = $query->start;
        }
        if ($limit == -1) {
            $limit = $query->limit;
        }
        if ($limit == -1) {
            $limit = SearchQuery::$default_page_size;
        }

        $params = array_merge($params, array('fq' => implode(' ', $fq)));

        $res = $service->search(
            $q ? implode(' ', $q) : '*:*',
            $offset,
            $limit,
            $params,
            \Apache_Solr_Service::METHOD_POST
        );

        $results = new ArrayList();
        if ($res->getHttpStatus() >= 200 && $res->getHttpStatus() < 300) {
            foreach ($res->response->docs as $doc) {
                $result = DataObject::get_by_id($doc->ClassName, $doc->ID);
                if ($result) {
                    $results->push($result);

                    // Add highlighting (optional)
                    $docId = $doc->_documentid;
                    if ($res->highlighting && $res->highlighting->$docId) {
                        // TODO Create decorator class for search results rather than adding arbitrary object properties
                        // TODO Allow specifying highlighted field, and lazy loading
                        // in case the search API needs another query (similar to SphinxSearchable->buildExcerpt()).
                        $combinedHighlights = array();
                        foreach ($res->highlighting->$docId as $field => $highlights) {
                            $combinedHighlights = array_merge($combinedHighlights, $highlights);
                        }

                        // Remove entity-encoded U+FFFD replacement character. It signifies non-displayable characters,
                        // and shows up as an encoding error in browsers.
                        $result->Excerpt = DBField::create_field(
                            'HTMLText',
                            str_replace(
                                '&#65533;',
                                '',
                                implode(' ... ', $combinedHighlights)
                            )
                        );
                    }
                }
            }
            $numFound = $res->response->numFound;
        } else {
            $numFound = 0;
        }

        $ret = array();
        $ret['Matches'] = new PaginatedList($results);
        $ret['Matches']->setLimitItems(false);
        // Tell PaginatedList how many results there are
        $ret['Matches']->setTotalItems($numFound);
        // Results for current page start at $offset
        $ret['Matches']->setPageStart($offset);
        // Results per page
        $ret['Matches']->setPageLength($limit);

        // Include spellcheck and suggestion data. Requires spellcheck=true in $params
        if (isset($res->spellcheck)) {
            // Expose all spellcheck data, for custom handling.
            $ret['Spellcheck'] = $res->spellcheck;

            // Suggestions. Requires spellcheck.collate=true in $params
            if (isset($res->spellcheck->suggestions->collation)) {
                // Extract string suggestion
                $suggestion = $this->getCollatedSuggestion($res->spellcheck->suggestions->collation);

                // The collation, including advanced query params (e.g. +), suitable for making another query
                // programmatically.
                $ret['Suggestion'] = $suggestion;

                // A human friendly version of the suggestion, suitable for 'Did you mean $SuggestionNice?' display.
                $ret['SuggestionNice'] = $this->getNiceSuggestion($suggestion);

                // A string suitable for appending to an href as a query string.
                // For example <a href="http://example.com/search?q=$SuggestionQueryString">$SuggestionNice</a>
                $ret['SuggestionQueryString'] = $this->getSuggestionQueryString($suggestion);
            }
        }

        $ret = new ArrayData($ret);

        // Enable extensions to add extra data from the response into
        // the returned results set.
        $this->extend('updateSearchResults', $ret, $res);

        return $ret;
    }

    /**
     * With a common set of variants that are relevant to at least one class in the list (from either the query or
     * the current index), allow them to alter the query to add their variant column conditions.
     *
     * @param SearchQuery $query
     */
    protected function applySearchVariants(SearchQuery $query)
    {
        $classes = count($query->classes) ? $query->classes : $this->getClasses();

        /** @var SearchVariant_Caller $variantCaller */
        $variantCaller = SearchVariant::withCommon($classes);
        $variantCaller->call('alterQuery', $query, $this);
    }

    /**
     * Solr requires namespaced classes to have double escaped backslashes
     *
     * @param  string $className   E.g. My\Object\Here
     * @param  string $replaceWith The replacement character(s) to use
     * @return string              E.g. My\\Object\\Here
     */
    public function sanitiseClassName($className, $replaceWith = '\\\\')
    {
        return str_replace('\\', $replaceWith, $className);
    }

    /**
     * Get the query (q) component for this search
     *
     * @param SearchQuery $searchQuery
     * @param array &$hlq Highlight query returned by reference
     * @return array
     */
    protected function getQueryComponent(SearchQuery $searchQuery, &$hlq = array())
    {
        $q = array();
        foreach ($searchQuery->search as $search) {
            $text = $search['text'];
            preg_match_all('/"[^"]*"|\S+/', $text, $parts);

            $fuzzy = $search['fuzzy'] ? '~' : '';

            foreach ($parts[0] as $part) {
                $fields = (isset($search['fields'])) ? $search['fields'] : array();
                if (isset($search['boost'])) {
                    $fields = array_merge($fields, array_keys($search['boost']));
                }
                if ($fields) {
                    $searchq = array();
                    foreach ($fields as $field) {
                        // Escape namespace separators in class names
                        $field = $this->sanitiseClassName($field);

                        $boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
                        $searchq[] = "{$field}:" . $part . $fuzzy . $boost;
                    }
                    $q[] = '+(' . implode(' OR ', $searchq) . ')';
                } else {
                    $q[] = '+' . $part . $fuzzy;
                }
                $hlq[] = $part;
            }
        }
        return $q;
    }

    /**
     * Parse all require constraints for inclusion in a filter query
     *
     * @param SearchQuery $searchQuery
     * @return array List of parsed string values for each require
     */
    protected function getRequireFiltersComponent(SearchQuery $searchQuery)
    {
        $fq = array();
        foreach ($searchQuery->require as $field => $values) {
            $requireq = array();

            foreach ($values as $value) {
                if ($value === SearchQuery::$missing) {
                    $requireq[] = "(*:* -{$field}:[* TO *])";
                } elseif ($value === SearchQuery::$present) {
                    $requireq[] = "{$field}:[* TO *]";
                } elseif ($value instanceof SearchQuery_Range) {
                    $start = $value->start;
                    if ($start === null) {
                        $start = '*';
                    }
                    $end = $value->end;
                    if ($end === null) {
                        $end = '*';
                    }
                    $requireq[] = "$field:[$start TO $end]";
                } else {
                    $requireq[] = $field . ':"' . $value . '"';
                }
            }

            $fq[] = '+(' . implode(' ', $requireq) . ')';
        }
        return $fq;
    }

    /**
     * Parse all exclude constraints for inclusion in a filter query
     *
     * @param SearchQuery $searchQuery
     * @return array List of parsed string values for each exclusion
     */
    protected function getExcludeFiltersComponent(SearchQuery $searchQuery)
    {
        $fq = array();
        foreach ($searchQuery->exclude as $field => $values) {
            // Handle namespaced class names
            $field = $this->sanitiseClassName($field);

            $excludeq = [];
            $missing = false;

            foreach ($values as $value) {
                if ($value === SearchQuery::$missing) {
                    $missing = true;
                } elseif ($value === SearchQuery::$present) {
                    $excludeq[] = "{$field}:[* TO *]";
                } elseif ($value instanceof SearchQuery_Range) {
                    $start = $value->start;
                    if ($start === null) {
                        $start = '*';
                    }
                    $end = $value->end;
                    if ($end === null) {
                        $end = '*';
                    }
                    $excludeq[] = "$field:[$start TO $end]";
                } else {
                    $excludeq[] = $field . ':"' . $value . '"';
                }
            }

            $fq[] = ($missing ? "+{$field}:[* TO *] " : '') . '-(' . implode(' ', $excludeq) . ')';
        }
        return $fq;
    }

    /**
     * @param SearchQuery $searchQuery
     * @return string
     * @throws \Exception
     */
    protected function getCriteriaComponent(SearchQuery $searchQuery)
    {
        if (count($searchQuery->getCriteria()) === 0) {
            return null;
        }

        if ($searchQuery->getAdapter() === null) {
            throw new \Exception('SearchQuery does not have a SearchAdapter applied');
        }

        // Need to start with a positive conjunction.
        $ps = $searchQuery->getAdapter()->getPrependToCriteriaComponent();

        foreach ($searchQuery->getCriteria() as $clause) {
            $clause->setAdapter($searchQuery->getAdapter());
            $clause->appendPreparedStatementTo($ps);
        }

        // Need to start with a positive conjunction.
        $ps .= $searchQuery->getAdapter()->getAppendToCriteriaComponent();

        // Returned as an array because that's how `getFiltersComponent` expects it.
        return $ps;
    }

    /**
     * Get all filter conditions for this search
     *
     * @param SearchQuery $searchQuery
     * @return array
     * @throws \Exception
     */
    public function getFiltersComponent(SearchQuery $searchQuery)
    {
        $criteriaComponent = $this->getCriteriaComponent($searchQuery);

        $components = array_merge(
            $this->getRequireFiltersComponent($searchQuery),
            $this->getExcludeFiltersComponent($searchQuery)
        );

        if ($criteriaComponent !== null) {
            $components[] = $criteriaComponent;
        }

        return $components;
    }

    protected $service;

    /**
     * @return SolrService
     */
    public function getService()
    {
        if (!$this->service) {
            $this->service = Solr::service(get_class($this));
        }
        return $this->service;
    }

    public function setService(SolrService $service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Upload config for this index to the given store
     *
     * @param SolrConfigStore $store
     */
    public function uploadConfig($store)
    {
        // Upload the config files for this index
        $store->uploadString(
            $this->getIndexName(),
            'schema.xml',
            (string)$this->generateSchema()
        );

        // Upload additional files
        foreach (glob($this->getExtrasPath() . '/*') as $file) {
            if (is_file($file)) {
                $store->uploadFile($this->getIndexName(), $file);
            }
        }
    }
}
