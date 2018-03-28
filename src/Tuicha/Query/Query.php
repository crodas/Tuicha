<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2018 César D. Rodas                                               |
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

use Tuicha;
use Tuicha\Metadata;
use ArrayAccess;
use IteratorIterator;
use MongoDB\Driver;
use MongoDB\Driver\Command;

class Query extends Cursor implements ArrayAccess
{
    protected $collection;
    protected $fields;
    protected $metadata;
    protected $namespace;
    protected $limit;

    public function __construct($metadata, $collection, $filter, $fields)
    {
        $this->metadata   = $metadata;
        $this->filter     = [];
        $this->fields     = $fields;
        $this->collection = $collection;
        $this->where($filter);
    }

    public function getFilter()
    {
        if ($this->metadata) {
            return $this->normalize($this->metadata, $this->filter);
        }

        return $this->filter;
    }

    protected function doQuery()
    {
        $options = ['selector' => $this->fields];
        if (is_numeric($this->limit) && $this->limit > 0) {
            $options['limit'] = $this->limit;
        }

        $query = new Driver\Query($this->getFilter(), $options);

        $cursor = $this->collection->query($query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'stdclass', 'array' => 'array']);
        $this->setResultSet(new IteratorIterator($cursor));
    }

    public function first()
    {
        $query = new Driver\Query($this->getFilter(), [
            'selector' => $this->fields,
            'limit' => 1,
        ]);

        $cursor = $this->collection->query($query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'stdclass', 'array' => 'array']);

        $result = $cursor->toArray();

        if (empty($result)) {
            return NULL;
        }

        return $this->metadata ? $this->metadata->newInstance($result[0]) : $result[0];
    }

    public function limit($limit)
    {
        $this->limit = (int)$limit;
        return $this;
    }

    public function count()
    {
        return Tuicha::command([
            'count' => $this->collection->getName(false),
            'query' => $this->getFilter(),
        ])->toArray()[0]->n;
    }
}
