<?php

namespace Tuicha;

use Tuicha;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;

/**
 * Base document
 *
 * Base document that all classes must inherits. It provides static methods, which are collection
 * operations and classes method which are document operations.
 *
 */
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
     * Finds a single documents in a collection or returns null.
     *
     * @param array $query
     * @param array $fields
     *
     * @return object
     */
    final static function find_one(Array $query = [], Array $fields = [])
    {
        $query = new Query(__CLASS__, $query, $fields);
        return $query->first();
    }

    /**
     * Finds one document in a collection or returns a new object
     *
     * @param array $query
     * @param array $fields
     *
     * @return object
     */
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
