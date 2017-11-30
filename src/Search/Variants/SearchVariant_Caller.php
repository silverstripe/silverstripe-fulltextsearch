<?php

namespace SilverStripe\FullTextSearch\Search\Variants;

/**
 * Internal utility class used to hold the state of the SearchVariant::with call
 */
class SearchVariant_Caller
{
    protected $variants = null;

    public function __construct($variants)
    {
        $this->variants = $variants;
    }

    public function call($method, &...$args)
    {
        $values = array();

        foreach ($this->variants as $variant) {
            if (method_exists($variant, $method)) {
                $value = $variant->$method(...$args);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }
}
