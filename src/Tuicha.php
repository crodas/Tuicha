<?php

use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
use MongoDB\Driver\BulkWrite;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;
use Tuicha\Metadata;
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
    protected static $autoload_loaded = false;

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
        self::$connections[$name] = compact('dbName', 'connection');
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

    public static function update($name, Array $selector, Array $update, $wait = true)
    {
        $connection = Metadata::of($name)->getConnection();
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

        return $connection['connection']->executeCommand($connection['dbName'], $command);
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
     * If $wait is true (default) this function will wait for a confirmation from the database
     * that the data has been successfuly stored. Otherwise this function will send the command
     * to MongoDB and forget about it, assuming it will not fail.
     *
     * @param object $object
     * @param bool $wait
     */
    public static function save($object, $wait = true)
    {
        $metadata = Metadata::of($object);
        $command = $metadata->getSaveCommand($object, true);
        if ($wait === true) {
            $wait = new WriteConcern(WriteConcern::MAJORITY);
        }

        switch ($command['command']) {
        case 'create':
            $writer = new BulkWrite;
            $writer->insert($command['document']);

            $return = $command['connection']->executeBulkWrite(
                $command['namespace'],
                $writer,
                $wait
            );

            break;

        case 'update':
            $queries = [];
            foreach ($command['document'] as $op => $value) {
                $queries[] = [
                    'q' => $command['selector'],
                    'multi' => false,
                    'u' => [$op => $value],
                    'upsert' => false
                ];
            }

            $return = static::command([
                'update' => $command['collection'],
                'updates' => $queries,
                'ordered' => true,
                'writeConcern' => $wait
            ])->toArray()[0];
            break;
        }

        if (!empty($return->writeErrors)) {
            var_dump($return->writeErrors);
        }


        $metadata->triggerEvent($object, 'after_save')
            ->snapshot($object);
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

        if (!self::$autoload_loaded) {
            $classes = & self::$autoload;
            spl_autoload_register(function($class) use (&$classes) {
                $class = strtolower($class);
                if (!empty($classes[$class])) {
                    require $classes[$class];
                }
            });
            self::$autoload_loaded = true;
        }
    }
}
