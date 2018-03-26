<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2017 César D. Rodas                                               |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/
namespace Tuicha\Query;

use Tuicha\Fluent\Filter;
use Iterator;

abstract class Cursor extends Filter implements Iterator
{
    protected $queried = false;
    protected $current;
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
        if ($this->current) {
            // The cursor started iterating, MongoDB driver does not
            // support rewinding after iterating, so rewind() in this
            // case should issue a new query
            $this->queried = false;
        }
        $this->ensureQuery();
        $this->result->rewind();
    }

    public function valid()
    {
        $this->ensureQuery();

        if ($this->result->valid()) {
            $document = $this->result->current();
            $this->current = $this->metadata ? $this->metadata->newInstance($document) : $document;
            return true;
        }

        return false;
    }

    public function next()
    {
        $this->ensureQuery();
        $this->result->next();
    }

    public function current()
    {
        return $this->current;
    }

    public function key()
    {
        $id = $this->metadata ? $this->metadata->getId($this->current) : $this->current['_id'];
        if (version_compare(PHP_VERSION, '5.5', '<=')) {
            $id = (string)$id;
        }
        return $id;
    }

}
