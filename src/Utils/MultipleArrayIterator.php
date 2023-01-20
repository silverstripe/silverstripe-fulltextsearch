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

    public function rewind(): void
    {
        $this->active = $this->arrays;
        if ($this->active) {
            reset($this->active[0]);
        }
    }

    public function current(): mixed
    {
        return $this->active ? current($this->active[0]) : false;
    }

    public function key(): mixed
    {
        return $this->active ? key($this->active[0]) : false;
    }

    public function next(): void
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

    public function valid(): bool
    {
        return $this->active && (current($this->active[0] ?? []) !== false);
    }
}
