<?php
namespace SilverStripe\FullTextSearch\Utils;

use Iterator;

class MultipleArrayIterator implements Iterator
{
    protected $arrays;
    protected $active;

    public function __construct()
    {
        $args = func_get_args();

        $this->arrays = array();
        foreach ($args as $arg) {
            if (is_array($arg) && count($arg ?? [])) {
                $this->arrays[] = $arg;
            }
        }

        $this->rewind();
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->active = $this->arrays;
        if ($this->active) {
            reset($this->active[0]);
        }
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->active ? current($this->active[0]) : false;
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->active ? key($this->active[0]) : false;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        if (!$this->active) {
            return;
        }

        if (next($this->active[0]) === false) {
            array_shift($this->active);
            if ($this->active) {
                reset($this->active[0]);
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->active && (current($this->active[0] ?? []) !== false);
    }
}
