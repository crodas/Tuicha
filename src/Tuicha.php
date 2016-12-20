<?php

use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
use MongoDB\Driver\BulkWrite;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\WriteConcern;
use Tuicha\Metadata;
use Remember\Remember;
use crodas\ClassInfo\ClassInfo;

class Tuicha
{
    protected static $connections = [];
    protected static $autoload = [];
    protected static $autoload_loaded = false;

    public static function addConnection($dbName, $connection = 'mongodb://localhost:27017', $name = 'default')
    {
        if (!($connection instanceof Manager)) {
            $connection = new Manager($connection);
        }
        self::$connections[$name] = compact('dbName', 'connection');
    }
    
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

    public static function dropDatabase($connection = 'default')
    {
        $response = Tuicha::command([
            'dropDatabase' => 1
        ], $connection)->toArray()[0];
        return (bool)$response->ok;
    }

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

    public static function save($object, $wait = true)
    {
        $command = Metadata::of($object)->getSaveCommand($object, true);
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


        Metadata::of($object)->snapshot($object);
    }

    public static function loadDocuments($path)
    {
        if (!is_dir($path)) {
            throw new RuntimeException("{$path} is not a valid directory");
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

        self::$autoload = array_merge($loader($path), self::$autoload);

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
