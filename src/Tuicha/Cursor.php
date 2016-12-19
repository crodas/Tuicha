<?php

namespace Tuicha;

use Iterator;

abstract class Cursor implements Iterator
{
    protected $queried = false;
    protected $result;

    abstract protected function doQuery();

    protected function setResultSet(Iterator $iterable)
    {
        $this->result  = $iterable;
        $this->queried = true;
    }

    protected function ensureQuery()
    {
        if (!$this->queried) {
            $this->doQuery();
        }
    }

    public function rewind()
    {
        $this->ensureQuery();
        $this->result->rewind();
    }

    public function valid()
    {
        $this->ensureQuery();
        return $this->result->valid();
    }

    public function next()
    {
        $this->ensureQuery();
        $this->result->next();
    }

    public function current()
    {
        $this->ensureQuery();
        return $this->metadata->newInstance($this->result->current());
    }

    public function key()
    {
        if (!$this->queried) {
            $this->doQuery();
        }
        return $this->metadata->getId($this->current);
    }

}
