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

namespace Tuicha;

use Tuicha;
use Tuicha\Query\Query;
use MongoDB\BSON\ObjectID;

/**
 * Base document
 *
 * Base document that all classes must inherits. It provides static methods, which are collection
 * operations and classes method which are document operations.
 *
 */
trait Document
{
    private $__lastInstance;
    private $__id;

    public function __setState(Array $state)
    {
        $this->__lastInstance = $state;
        if (!empty($state['_id'])) {
            $this->__id = $state['_id'];
        }
    }

    public function __getState()
    {
        return $this->__lastInstance;
    }

    /**
     * Finds documents in a collection.
     *
     * @param array $query
     * @param array $fields
     *
     * @return Tuicha\Query
     */
    final static function find(Array $query = [], Array $fields = [])
    {
        return new Query(__CLASS__, $query, $fields);
    }

    /**
     * Finds one document in a collection or returns a new object
     *
     * @param array $query
     * @param array $fields
     *
     * @return object
     */
    final static function firstOrNew(Array $query)
    {
        $doc = self::find($query)->first();
        if ($doc) {
            return $doc;
        }
        $doc = new static;
        foreach ($query as $key => $val) {
            $doc->$key = $val;
        }
        return $doc;
    }

    /**
     * Finds one document in a collection or creates a new document and returns
     * it as an object.
     *
     * @param array $query
     * @param array $fields
     *
     * @return object
     */
    final static function firstOrCreate(Array $query)
    {
        $doc = self::firstOrNew($query);
        $doc->save();
        return $doc;
    }

    /**
     * Creates indexes
     *
     * Creates indexes in this collection. All the information is provided by the Metadata object.
     *
     * @return MongoDB\Driver\Cursor
     */
    final public static function createIndex()
    {
        return Tuicha::command([
            'createIndexes' => Metadata::of(__CLASS__)->getCollectionName(),
            'indexes' => Metadata::of(__CLASS__)->getIndexes(),
        ]);
    }

    /**
     * Counts how many records matches a query.
     *
     * @param array $query
     *
     * @return int
     */
    final static function count(Array $query = [])
    {
        $q = new Query(__CLASS__, $query, []);
        return $q->count();
    }

    /**
     * Updates documents matching a selector.
     *
     * @param array $selector
     * @param array $document
     * @param boolean $upsert
     * @param boolean $multi
     * @param boolean $wait
     *
     * @return MongoDB\Driver\Cursor
     */
    final static function update($where = null, $set = null)
    {
        $metadata = Metadata::of(__CLASS__);
        $query = Tuicha::update($metadata->getCollectionName());

        if ($where !== null) {
            $query->where($where);
        }

        if ($set !== null) {
            $query->set($set);
        }

        return $query;
    }

    /**
     * Saves the changes in the current document/object.
     *
     * @param boolean $wait
     *
     * @return bool
     */
    public function save($wait = true)
    {
        return Tuicha::save($this, $wait);
    }
}
