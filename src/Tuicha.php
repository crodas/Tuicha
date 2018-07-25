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

use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
use MongoDB\Driver\BulkWrite;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;
use Tuicha\Database;
use Tuicha\Collection;
use Tuicha\Operation;
use Tuicha\Metadata;
use Tuicha\Query\Update;
use Tuicha\Query\Delete;
use Remember\Remember;
use crodas\ClassInfo\ClassInfo;

/**
 * Tuicha class
 *
 * This is the main class of the project. It exposes public API for creating and managing database connections,
 * and perform databases operations.
 */
class Tuicha
{
    protected static $connections = [];
    protected static $autoload = [];
    protected static $dirs = [];
    protected static $autoload_loaded = false;
    protected static $saving = [];

    /**
     * Adds a new connection,
     *
     * Adds a new connection to the connection pool. Each connection provides the database name, address
     * and a name.
     *
     * Old connections maybe be replace at any time, the third parameter is the connection name.
     *
     * @param string $dbName
     * @param string $connection
     * @param string $name
     */
    public static function addConnection($dbName, $connection = 'mongodb://localhost:27017', $name = 'default')
    {
        if (!($connection instanceof Manager)) {
            $connection = new Manager($connection);
        }
        self::$connections[$name] = new Database($dbName, $connection);
    }

    /**
     * Returns the an array with the connection, or an exception if the connection is not defined. The returned
     * array has two elements, the database namae and teh MongoDB connection manager object.
     *
     * @return Array
     */
    public static function getConnection($connectionName = 'default')
    {
        if (empty(self::$connections[$connectionName])) {
            throw new RuntimeException("Cannot find connection {$connectionName}");
        }

        return self::$connections[$connectionName];
    }

    /**
     * Executes a find query
     *
     * This is meant for a low level API access only. It allows querying directly to a MongoDB collection.
     *
     * All queries though be going through the `find`, `where` or `newQuery` static methods provided by
     * the Document trait.
     *
     * @param string $collectionName    Collection. It could also be a class name
     * @param array  $query             Query. The query can be changed through the fluent API
     * @param string $connection        Connection name
     * @param bool   $raw               If TRUE the result will be returned as an array
     *
     * @return Tuicha\Query\Query
     */
    public static function find($collectionName, $query = [], $connection = 'default', $raw = false)
    {
        $class = class_exists($collectionName) ? $collectionName : self::getCollectionClass($collectionName);
        $class = $raw ? false : $class;
        $metadata   = $class ? Metadata::of($class) : null;
        $collection = $metadata ? $metadata->getCollection() : new Collection($collectionName, self::getConnection($connection));


        return new Tuicha\Query\Query($metadata, $collection, [$query]);
    }

    /**
     * Creates an update object
     *
     * Creates an update object for a collectionName through a connection.
     *
     * The update object has an fluent interface that allows to modify the update
     * before sending it to the database.
     *
     * @param string $collectionName    The collection name
     * @param string $connection        The connection name
     *
     * @return Tuicha\Query\Update
     */
    public static function update($collectionName, $connection = 'default')
    {
        $metadata = null;
        if (class_exists($collectionName)) {
            $metadata       = Metadata::of($collectionName);
            $collectionName = $metadata->getCollectionName();
            //$connection     = $metadata->getConnectionName();
        }
        return new Update($metadata, $collectionName, self::getConnection($connection));
    }

    /**
     * Creates an delete object
     *
     * Creates an delete object for a collectionName through a connection.
     *
     * The delete object has an fluent interface.
     *
     * @param string $collectionName    The collection name
     * @param string $connection        The connection name
     *
     * @return Tuicha\Query\Delete
     */
    public static function delete($collectionName, $connection = 'default')
    {
        $metadata = null;
        if (class_exists($collectionName)) {
            $metadata       = Metadata::of($collectionName);
            $collectionName = $metadata->getCollectionName();
            //$connection     = $metadata->getConnectionName();
        }
        return new Delete($metadata, $collectionName, self::getConnection($connection));
    }

    /**
     * Deletes a database.
     *
     * @param string $connectionName
     *
     * @return bool
     */
    public static function dropDatabase($connectionName = 'default')
    {
        $response = Tuicha::command([
            'dropDatabase' => 1
        ], $connectionName)->toArray()[0];
        return (bool)$response->ok;
    }

    /**
     * Executes a command in the database.
     *
     * The database command can be a Command object or an array. The $connection argument chooses
     * in which connection to execute the given command, it will choose the 'default' conection if
     * this argument is NULL.
     *
     * @param $command
     * @param string $connection
     *
     * @return MongoDB\Driver\Cursor
     */
    public static function command($command, $connection = null)
    {
        if (!($command instanceof Command)) {
            $command = new Command($command);
        }

        if (is_string($connection)) {
            $connection = self::getConnection($connection);
        }

        $connection = $connection ?: self::getConnection();
        if ($connection instanceof Collection) {
            $connection = $connection->getDatabase();
        }

        return $connection->execute($command);
    }

    /**
     * Saves an object into MongoDB.
     *
     * Saves an object into MongoDB. This function will save any object, even objects without 
     * any metadata nor trait inheratance. In those cases the default data will be used.
     *
     * This save function will perform either an insert or an update if the object was created
     * by the result from a query to the database.
     *
     * If the operation is an update, only those properties with changes are saved back into the
     * database instead of pushing the entire object back.
     *
     * When the save or update command an snapshot of the object is created privately. It is used
     * for future save() calls, to persists only those properties which have changed.
     *
     * If $wait is true (default) this function will wait for a confirmation from the database
     * that the data has been successfuly stored. Otherwise this function will send the command
     * to MongoDB and forget about it, assuming it will not fail.
     *
     * @param object $object
     * @param bool $wait
     */
    public static function save($object, $wait = true)
    {
        $objectId = spl_object_hash($object);
        if (!empty(self::$saving[$objectId])) {
            return false;
        }

        self::$saving[$objectId] = true;

        try {
            $ret = self::_save($object, $wait);
        } catch (Exception $e) {
            unset(self::$saving[$objectId]);
            throw $e;
        }

        unset(self::$saving[$objectId]);
        return $ret;
    }

    /**
     * Internal implementation of the save() command.
     *
     * The public interface has a protection to avoid infinite recursion when saving objects
     * with references. This raw function does not have this protection but rather the saving
     * implementation.
     *
     * @param object $object
     * @param bool $wait
     */
    private static function _save($object, $wait)
    {
        $metadata = Metadata::of($object);
        $command  = $metadata->getSaveCommand($object);
        $wait     = $wait ? new WriteConcern(WriteConcern::MAJORITY) : null;

        // There is nothing to create/update
        if (empty($command['document'])) {
            return;
        }

        $writer = new BulkWrite;
        switch ($command['command']) {
        case 'create':
            $writer->insert($command['document']);
            break;

        case 'update':
            foreach ($command['document'] as $op => $value) {
                $writer->update(
                    $command['selector'],
                    [$op => $value],
                    ['multi' => false, 'upsert' => false]
                );
            }
            break;
        }

        $return = $command['connection']->executeBulkWrite(
            $command['namespace'],
            $writer,
            $wait
        );

        $metadata->triggerEvent($object, 'saved')
            ->triggerEvent($object, $command['command'] === 'create' ? 'created' : 'updated')
            ->snapshot($object, $command['snapshot']);


        return $return;
    }

    /**
     * Loads classes in a directory
     *
     * Loads all the classes and their metadata in a given directory. For efficiency the metadata
     * is cached. As a bonus, it will also register an autoloader, which is cached.
     *
     * @param string $directory
     *
     * @return void
     */
    public static function addDirectory($directory)
    {
        if (!is_dir($directory)) {
            throw new RuntimeException("{$directory} is not a valid directory");
        }

        $loader = Remember::wrap('tuicha-autoload', function($args, $files) {
            $files = array_filter($files, 'is_file');
            $parser = new ClassInfo;
            foreach ($files as $file) {
                $parser->parse($file);
            }
            $classes = [];
            foreach ($parser->getClasses() as $class) {
                $classes[strtolower($class->getName())] = $class->getFile();
            }

            return array_filter($classes);
        });

        self::$autoload = array_merge($loader($directory), self::$autoload);
        self::$dirs[]   = $directory;

        if (!self::$autoload_loaded) {
            spl_autoload_register(__CLASS__ . '::autoloader');
            self::$autoload_loaded = true;
        }
    }

    /**
     * Creates a database reference to the current object.
     *
     * MongoDB references are a standard way of referencing another document within the same database.
     *
     * Tuicha will dereference the object automatically at run-time if needed. References created by Tuicha
     * may cache some properties. These cached properties will be stored in the reference structure.
     *
     * Cached properties are helpful to avoid dereferencing (loading the referenced document from the database)
     * when reading the property. lease notice that Tuicha does not update the cached properties should the 
     * object is updated.
     *
     * Optionally references may be flagged as 'read-only'. That means that any modifications will be ignored, by
     * default all references are not read-only and any modifications will be persisted in the referenced document.
     *
     * @param object $object        Object to reference
     * @param array  $fields        Which fields should be cached within the reference
     * @param bool   $readOnly      Whether to flag the reference as read-only or not.
     *
     * @return Tuicha\Reference
     */
    public static function makeReference($object, $with = [], $readOnly = false)
    {
        return Metadata::of($object)->makeReference($object, $with, $readOnly);
    }

    /**
     * Returns the class name associated with a collection name
     *
     * @param string $collection    Collection Name
     *
     * @return string|bool  Returns the class name or false.
     */
    public static function getCollectionClass($collection)
    {
        $loader = Remember::wrap('tuicha-classes', function($classes) {
            $collections = [];
            $classes     = array_filter($classes, 'class_exists');
            foreach ($classes as $id => $class) {
                $metadata = Metadata::of($class);
                if (!$metadata->hasOwnCollection()) {
                    unset($classes[$id]);
                    continue;
                }
                $collections[] = $metadata->getCollectionName();
            }

            return array_combine($collections, $classes);
        });

        $collectionsClasses = $loader(array_keys(self::$autoload));

        return empty($collectionsClasses[$collection]) ? false : $collectionsClasses[$collection];
    }

    /**
     * Autoloader
     *
     * Implements an autoloader which adds folders through `Tuicha::addDirectory`.
     *
     * The autoloader is rather simple, the data comes through `Tuicha::addDirectory` and
     * it is cached for performance.
     *
     * @param string $class Class name to autoload
     *
     * @return bool
     */
    public static function autoloader($class)
    {
        $class = strtolower($class);
        if (!empty(self::$autoload[$class])) {
            require self::$autoload[$class];
            return true;
        }

        return false;
    }
}
