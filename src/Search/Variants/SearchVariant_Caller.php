<?php

namespace SilverStripe\FullTextSearch\Search\Variants;

/**
 * Internal utility class used to hold the state of the SearchVariant::with call
 */
class SearchVariant_Caller
{
    public function __construct(protected $variants)
    {
    }

    public function call($method, &...$args)
    {
        $values = [];

        foreach ($this->variants as $variant) {
            if (method_exists($variant, $method ?? '')) {
                $value = $variant->$method(...$args);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }
}
