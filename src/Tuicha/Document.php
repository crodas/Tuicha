<?php

namespace Tuicha;

use Tuicha;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;

Trait Document
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

    final static function find(Array $query = [], Array $fields = [])
    {
        return new Query(__CLASS__, $query, $fields);
    }

    final static function find_one(Array $query = [], Array $fields = [])
    {
        $query = new Query(__CLASS__, $query, $fields);
        return $query->first();
    }

    final static function find_or_create(Array $query)
    {
        $doc = self::find_one($query);
        if ($doc) {
            return $doc;
        }
        $doc = new static;
        foreach ($query as $key => $val) {
            $doc->$key = $val;
        }
        return $doc->save();
    }

    public static function createIndex()
    {
        return Tuicha::command([
            'createIndexes' => Metadata::of(__CLASS__)->getCollectionName(),
            'indexes' => Metadata::of(__CLASS__)->getIndexes(),
        ]);
    }

    final static function count($query = [])
    {
        $q = new Query(__CLASS__, $query, []);
        return $q->count();
    }

    final static function update(Array $selector, Array $document, $upsert = false, $multi = true, $wait = true)
    {
        if ($wait === true) {
            $wait = new WriteConcern(WriteConcern::MAJORITY);
        }

        $update = Tuicha::command([
            'update' => Metadata::of(__CLASS__)->getCollectionName(),
            'updates' => [
                ['q' => $selector, 'u' => $document, 'upsert' => $upsert, 'multi' => $multi],
            ],
            'ordered' => true,
            'writeConcern' => $wait,
        ]);

        return $update;
    }

    public function save($wait = true)
    {
        return Tuicha::save($this, $wait);
    }
}
