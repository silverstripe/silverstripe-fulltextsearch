<?php

namespace SilverStripe\FullTextSearch\Utils;

use Iterator;

class CombinationsArrayIterator implements Iterator
{
    protected $arrays;
    protected $keys;
    protected $numArrays;

    protected $isValid = false;
    protected $k = 0;

    public function __construct($args)
    {
        $this->arrays = array();
        $this->keys = array();

        $keys = array_keys($args);
        $values = array_values($args);

        foreach ($values as $i => $arg) {
            if (is_array($arg) && count($arg)) {
                $this->arrays[] = $arg;
                $this->keys[] = $keys[$i];
            }
        }

        $this->numArrays = count($this->arrays);
        $this->rewind();
    }

    public function rewind()
    {
        if (!$this->numArrays) {
            $this->isValid = false;
        } else {
            $this->isValid = true;
            $this->k = 0;
            
            for ($i = 0; $i < $this->numArrays; $i++) {
                reset($this->arrays[$i]);
            }
        }
    }

    public function valid()
    {
        return $this->isValid;
    }

    public function next()
    {
        $this->k++;

        for ($i = 0; $i < $this->numArrays; $i++) {
            if (next($this->arrays[$i]) === false) {
                if ($i == $this->numArrays-1) {
                    $this->isValid = false;
                } else {
                    reset($this->arrays[$i]);
                }
            } else {
                break;
            }
        }
    }

    public function current()
    {
        $res = array();
        for ($i = 0; $i < $this->numArrays; $i++) {
            $res[$this->keys[$i]] = current($this->arrays[$i]);
        }
        return $res;
    }

    public function key()
    {
        return $this->k;
    }
}
