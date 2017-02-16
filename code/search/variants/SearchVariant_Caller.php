<?php
/**
 * Created by PhpStorm.
 * User: elliot
 * Date: 21/04/17
 * Time: 1:13 PM
 */

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

    public function call($method, &$a1=null, &$a2=null, &$a3=null, &$a4=null, &$a5=null, &$a6=null, &$a7=null)
    {
        $values = array();

        foreach ($this->variants as $variant) {
            if (method_exists($variant, $method)) {
                $value = $variant->$method($a1, $a2, $a3, $a4, $a5, $a6, $a7);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }
}