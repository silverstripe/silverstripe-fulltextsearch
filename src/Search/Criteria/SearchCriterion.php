<?php

namespace SilverStripe\FullTextSearch\Search\Criteria;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\FullTextSearch\Search\Adapters\SearchAdapterInterface;
use SilverStripe\FullTextSearch\Search\Queries\AbstractSearchQueryWriter;

/**
 * Class SearchCriterion
 * @package SilverStripe\FullTextSearch\Search\Criteria
 */
class SearchCriterion implements SearchCriteriaInterface
{
    use Injectable;

    /**
     * field:value
     *
     * @var string
     */
    const EQUAL = 'EQUAL';

    /**
     * -field:value
     *
     * @var string
     */
    const NOT_EQUAL = 'NOT_EQUAL';

    /**
     * field:[value TO *]
     *
     * @var string
     */
    const GREATER_EQUAL = 'GREATER_EQUAL';

    /**
     * field:{value TO *}
     *
     * @var string
     */
    const GREATER_THAN = 'GREATER_THAN';

    /**
     * field:[* TO value]
     *
     * @var string
     */
    const LESS_EQUAL = 'LESS_EQUAL';

    /**
     * field:{* TO value}
     *
     * @var string
     */
    const LESS_THAN = 'LESS_THAN';

    /**
     * (field:value1 field:value2 field:value3)
     *
     * @var string
     */
    const IN = 'IN';

    /**
     * -(field:value1 field:value2 field:value3)
     *
     * @var string
     */
    const NOT_IN = 'NOT_IN';

    /**
     * field:[* TO *]
     *
     * @var string
     */
    const ISNULL = 'ISNULL';

    /**
     * -field:[* TO *]
     *
     * @var string
     */
    const ISNOTNULL = 'ISNOTNULL';

    /**
     * A custom Criterion with it's own SearchQueryWriter
     *
     * @var string
     */
    const CUSTOM = 'CUSTOM';

    /**
     * @var string
     */
    protected $comparison;

    /**
     * The table and field that this Criterion is applied to.
     *
     * @var string
     */
    protected $target;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var SearchAdapterInterface
     */
    protected $adapter;

    /**
     * @var AbstractSearchQueryWriter
     */
    protected $searchQueryWriter;

    /**
     * @param string $target
     * @param string|array $value
     * @param string|null $comparison
     * @param AbstractSearchQueryWriter $searchQueryWriter
     */
    public function __construct(
        $target,
        $value,
        $comparison = null,
        AbstractSearchQueryWriter $searchQueryWriter = null
    ) {
        // EQUAL is our default comparison.
        if ($comparison === null) {
            $comparison = SearchCriterion::EQUAL;
        }

        $this->setTarget($target);
        $this->setValue($value);
        $this->setComparison($comparison);
        $this->setSearchQueryWriter($searchQueryWriter);
    }

    /**
     * @return SearchAdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param SearchAdapterInterface $adapter
     * @return $this
     */
    public function setAdapter(SearchAdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param string $ps
     * @return void
     * @throws \Exception
     */
    public function appendPreparedStatementTo(&$ps)
    {
        $adapter = $this->getAdapter();

        if (!$adapter instanceof SearchAdapterInterface) {
            throw new \Exception('No adapter has been applied to SearchCriteria');
        }

        $ps .= $adapter->generateQueryString($this);
    }

    /**
     * String values should be passed into our filter string with quotation marks and escaping.
     *
     * @param string $value
     * @return string
     */
    public function getQuoteValue($value)
    {
        if (is_string($value)) {
            return sprintf('"%s"', $value);
        }

        return $value;
    }

    /**
     * @return AbstractSearchQueryWriter
     */
    public function getSearchQueryWriter()
    {
        return $this->searchQueryWriter;
    }

    /**
     * @param AbstractSearchQueryWriter $searchQueryWriter
     * @return $this
     */
    public function setSearchQueryWriter($searchQueryWriter)
    {
        $this->searchQueryWriter = $searchQueryWriter;

        return $this;
    }

    /**
     * @return string
     */
    public function getComparison()
    {
        return $this->comparison;
    }

    /**
     * @param string|null $comparison
     * @return $this
     */
    protected function setComparison($comparison)
    {
        $this->comparison = $comparison;

        return $this;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param string $target
     * @return $this
     */
    protected function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string|array $value
     * @return $this
     */
    protected function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
