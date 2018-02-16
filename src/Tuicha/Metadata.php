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
use Remember\Remember;
use RuntimeException;
use Notoj\Annotation\Annotation;
use Notoj\Annotation\Annotations;
use InvalidArgumentException;
use Datetime;
use UnexpectedValueException;
use MongoDB\BSON\UTCDateTime;
use Doctrine\Common\Inflector\Inflector;
use Notoj\ReflectionClass;
use Notoj\ReflectionProperty;
use Notoj\ReflectionMethod;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Type;

/**
 * Metadata
 *
 * This metadata object is a reflection-like meta class which exposes details about how Tuicha should
 * treat the documents and the collection.
 *
 * Its constructor is private, so it should be used through its public interface, `Metadata::of(<className>)`.
 *
 * Because it extracts information using the reflection API and parsing annotations the metadata
 * are cached to disk for efficiency. Although any modification to the original file where the class is defined
 * will invalidate the metadata cache.
 *
 * Beside caching this class also provide run time capabilites which helps Tuicha.
 */
class Metadata
{
    protected static $allEvents = [
        'before_create',
        'before_update',
        'before_save',
        'after_save',
    ];

    protected $className;
    protected $collectionName;
    protected $idProperty = null;
    protected $file;
    protected $hasTrait = false;
    protected $pProps  = [];
    protected $mProps  = [];
    protected $indexes = [];
    protected $events = [];
    protected static $instances = [];

    /**
     * Metadata extraction functions
     *
     * These methods are used during the metadata construction process.
     *
     * Because it may be expensive to do it over an over it is cached.
     */

    /**
     * Class constructor
     *
     * This method is private on porpuse, by doing so it is not possible to construct outside of this scope.
     *
     * The only way of creating a Metadata object is through the `of` static method. This method will
     * either create a new object (which expensive because it has to read all the class metadata) or it
     * will unserialize from cache (the best scenario).
     *
     * @return this
     */
    final private function __construct($className)
    {
        $this->className = $className;
        $this->readClassMetadata();
    }

    /**
     * Reads the every metadata associated with this class.
     *
     * Extract information about:
     *  - Properties
     *  - Methods
     *  - Events
     *
     * The end result is stored in cached for efficiency. The cache storage
     * and invalidation is handled by `crodas\Remember`.
     *
     * @return void
     */
    protected function readClassMetadata()
    {
        if (!class_exists($this->className)) {
            throw new RuntimeException("Cannot find the class {$this->className}");
        }

        $reflection = new ReflectionClass($this->className);
        $this->file = $reflection->getFileName();
        $this->hasTrait = in_array(__NAMESPACE__ . '\Document', $reflection->getTraitNames());

        if ($reflection->getAnnotations()->has('persist,table,collection')) {
            $collection = $reflection->getAnnotations()->getOne('persist,table,collection')->getArg(0);
        } else {
            $class = explode("\\", $this->className);
            $collection = strtolower(Inflector::pluralize(end($class)));
        }

        $this->collectionName = $collection;

        foreach ($reflection->getProperties() as $property) {
            $this->processProperty($property);
        }

        foreach ($reflection->getMethods() as $method) {
            $this->processMethod($method);
        }

        if (!$this->idProperty) {
            $definition = [
                'annotations' => [],
                'validations' => [],
                'required' => false,
                'is_public' => true,
                'is_private' => false,
                'type' => 'id',
                'mongoProp' => '_id',
                'phpProp' => 'id',
            ];

            $this->pProps['id'] = $definition;
            $this->mProps['_id'] = $definition;
            $this->idProperty  = 'id';
        }
    }

    /**
     * Returns a metadata object associated with a class name.
     *
     * This method will return a Metadata object associated with a class name.
     *
     * This method ensures the object is constructed at most once per request. This method
     * also caches the object for efficiency. All the cache storing and invalidation
     * is handled by `crodas\Remember`.
     *
     * @param string $className The class name
     *
     * @return Metadata object.
     */
    public static function of($className)
    {
        static $loader;
        if (is_object($className)) {
            $className = get_class($className);
        }

        if (empty($loader)) {
            $loader = Remember::wrap('tuicha', function(&$args) {
                // The object is not in cache or it is not longer valid
                // Therefore these things are happening:
                //    - A new object is created.
                //    - That object is both returned and cached.
                //    - The file where the class is defined is added
                //      to the file list. Any modification to this file
                //      will invalidate the cached data.
                $metadata = new self($args[0]);
                $args[] = $metadata->getFile();
                return $metadata;
            });
        }

        if (empty(self::$instances[$className])) {
            self::$instances[$className] = $loader($className);
        }

        return self::$instances[$className];
    }

    /**
     * Serializes a PHP value to store in MongoDB
     *
     *   1. Scalar values and MongoDB\BSON\Type objects are stored as is.
     *   2. Any property that begins with __ is ignored (not persisted).
     *   3. Any resource is ignored.
     *   4. PHP's Datetime objects are converted to MongoDB\BSON\UTCDateTime
     *   5. Any object is serialized with their own Metadata object (Metadata::serializeValue)
     *
     * @param string $propertyName  Property name
     * @param mixed  &$value        Value to serialize. It is by reference, it is OK to edit it in place.
     * @param boolean $validate     Whether or not to validate
     *
     * @return boolean TRUE if the property was serialized, FALSE if it should be ignored.
     */
    protected function serializeValue($propertyName, &$value, $validate = true)
    {
        if (substr($propertyName, 0, 2) === '__' || is_resource($value)) {
            return false;
        }

        if ($value instanceof Type || is_scalar($value)) {
            return true;
        }

        if ($value instanceof Datetime) {
            $value = new UTCDateTime($value);
            return true;
        }

        if (is_object($value)) {
            $class = strtolower(get_class($value));
            $value = Metadata::of($value)->toDocument($value, $validate);
            $definition = empty($this->pProps[$propertyName]) ? NULL : $this->pProps[$propertyName];
            if (!$definition || empty($definition['type']['class']) || strtolower($definition['type']['class']) !== $class) {
                // Tuicha must save the object class name to be able to populate it back.
                $value['__$type'] = compact('class');
            }
        }

        return true;
    }

    /**
     * Returns all the arguments from an array of annotations
     *
     * @param array $annotations An array of Notoj\Annotation\Annotation objects
     *
     * @return array
     */
    protected function getAnnotationArguments(Array $annotations)
    {
        $arguments = [];
        foreach ($annotations as $annotation) {
            foreach ($annotation->getArgs() as $arg) {
                $arguments[] = $arg;
            }
        }

        foreach ($arguments as $id => $function) {
            $args = [];
            if ($function instanceof Annotation) {
                $args     = $function->getArgs();
                $function = $function->getName();
            }

            if (is_callable([__NAMESPACE__ . '\Validation', $function])) {
                $function = [__NAMESPACE__ . '\Validation', $function];
            } else if (is_string($function) && strpos($function, "::") > 0) {
                $function = explode("::", $function, 2);
            }
            $arguments[$id] = [$function, $args];
        }

        return $arguments;
    }

    /**
     * Adds an array definition to the Metadata object.
     *
     * @return void
     */
    protected function defineIndex(Array $index)
    {
        $name = [!empty($index['unique']) ? 'unique' : 'index'];
        foreach ($index['key'] as $field => $asc) {
            $name[] = $field . '_' . ($asc ? 'asc' : 'desc');
        }

        $index['name'] = implode('_', $name);
        $this->indexes[]= $index;
    }

    /**
     * Processes Indexes defined in properties
     *
     * Creates indexes and unique indexes defined in properties.
     *
     * @param array $propData           The property definition
     * @param Annotations $annotations  All the annotations defined in the property
     *
     * @return void
     */
    protected function processPropertyIndexes(Array $propData, Annotations $annotations)
    {
        $index = $annotations->getOne('index,unique');
        if (!$index) {
            return;
        }

        $args = $index->getArgs();
        if (empty($args['asc']) && empty($args['desc'])) {
            $order = 1;
        } else if (!empty($args['asc'])) {
            $order = !empty($args['asc']) ? 1 : -1;
        } else {
            $order = empty($args['desc']) ? 1 : -1;
        }
        $this->defineIndex([
            'key' => [$propData['mongoProp'] => $order],
            'unique' => $index->getName() === 'unique',
            'sparse' => !empty($args['sparse']),
            'background' => true,
        ]);
    }

    /**
     * Processes properties
     *
     * Processes properties from a class and extracts all the metadata for future
     * usage.
     *
     * The metadata collected includes:
     *    - Property name (PHP)
     *    - Key name (stored in MongoDB)
     *    - Datatype for conversion and/or validation
     *    - Any index that may be defined.
     *
     * @param Notoj\ReflectionProperty $property
     *
     * @return void
     */
    protected function processProperty(ReflectionProperty $property)
    {
        $annotations = $property->getAnnotations();
        $phpName     = $property->getName();
        $mongoName   = $annotations->has('field') ? $annotations->getOne('field')->getArg(0) : $phpName;

        if (!$property->isPublic() && substr($phpName, 0, 2) === '__') {
            return;
        }

        if ($annotations->has('id')) {
            $mongoName = '_id';
            $this->idProperty = $phpName;
        }

        $propData    = [
            'annotations' => [],
            'validations' => $this->getAnnotationArguments($annotations->get('validate')),
            'required'    => $annotations->has('required'),
            'is_public'   => $property->isPublic(),
            'is_private'  => $property->isPrivate(),
            'type'        => $annotations->has('type') ? $annotations->getOne('type')->getArgs() : NULL,
            'mongoProp'   => $mongoName,
            'phpProp'     => $phpName,
        ];

        $this->processPropertyIndexes($propData, $annotations);

        foreach ($annotations as $annotation) {
            $propData['annotations'][] = [$annotation->getName(), $annotation->getArgs()];
        }

        $this->pProps[$phpName]   = $propData;
        $this->mProps[$mongoName] = $propData;
    }

    /**
     * Returns an array of all the annotations and the events they represent.
     *
     * @return array.
     */
    protected function getAllEventAnnotations()
    {
        $events = [];
        foreach (self::$allEvents as $event) {
            $events[$event] = $event;
            $events[str_replace("_", "", $event)] = $events;
        }

        return $events;
    }

    /**
     * Processes methods from a class.
     *
     * Processes methods from a class, if they have any annotation which represent an
     * event it will be recorded in the class metadata object.
     *
     * @return void
     */
    protected function processMethod(ReflectionMethod $method)
    {
        $events = $this->getAllEventAnnotations();
        $annotations = implode(",", array_keys($events));

        if (!$method->getAnnotations()->has($annotations)) {
            return;
        }

        foreach ($method->getAnnotations()->get($annotations) as $annotation) {
            $event = $events[$annotation->getName()];
            $this->events[$event] = [
                'method' => $method->getName(),
                'is_public' => $method->isPublic(),
                'args' => $annotation->getArgs(),
            ];
        }
    }


    /**
     * Runtime functions
     *
     * These functions exposes information about the metadata associated with a class
     * (and its collection).
     *
     * These functions may also perform some operations needed by Tuicha
     */

    /**
     * Returns all the indexes defined for this class (and its collection)
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Returns the filename where the current class is defined.
     *
     * @retun string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Returns information about the connection to this collection
     *
     * @return array
     */
    public function getCollection()
    {
        static $cache = [];
        if (empty($cache[$this->className])) {
            $connection = Tuicha::getConnection('default');
            $cache[$this->className] = new Collection($this->getCollectionName(), $connection);
        }

        return $cache[$this->className];
    }

    /**
     * Returns the collection name.
     *
     * If $dbName is true, the database name is prepend to the collection name.
     *
     * @param bool $dbName Whether to include the database name or not.
     *
     * @return string
     */
    public function getCollectionName($dbName = false)
    {
        if ($dbName) {
            return $this->getCollection()->getName();
        }
        return $this->collectionName;
    }

    /**
     * Triggers an event.
     *
     * This function triggers an event in a given object.
     *
     * @param object $object    Object to execute the event.
     * @param string $eventName Event name
     *
     * @return $this
     */
    public function triggerEvent($object, $eventName)
    {
        if (empty($this->events[$eventName])) {
            return $this;
        }

        if (!($object instanceof $this->className)) {
            throw new RuntimeException("Invalid object, expecteding a {$this->className} object");
        }

        foreach ($this->events as $event) {
            if ($event['is_public']) {
                $object->{$event['method']}($event['args']);
            } else {
                throw new RuntimeException("Only public methods are supported for now");
            }
        }
        return $this;
    }

    /**
     * Creates a new instance of a given $type
     *
     * @param array $type
     * @param array $document
     *
     * @return object
     */
    protected function newInstanceByType($type, $document)
    {
        if (!empty($type['class'])) {
            return Metadata::of($type['class'])->newInstance($document);
        }

        return $document;
    }

    /**
     * Creates a new instance object.
     *
     * This function will take a document from the database (an array) and will
     * return a PHP object. It uses the metadata if available.
     *
     * @param array $document
     *
     * @return object
     */
    public function newInstance(array $document)
    {
        $class  = $this->className;
        $object = new $this->className;
        foreach ($document as $key => $value) {
            $prop = null;
            if (!empty($this->pProps[$key]) ||  !empty($this->mProps[$key])) {
                $prop = !empty($this->pProps[$key]) ? $this->pProps[$key] : $this->mProps[$key];
                $key  = $prop['phpProp'];
            }

            if (!empty($prop['type'])) {
                $value = $this->newInstanceByType($prop['type'], $value);
            } else if (is_object($value) && !empty($value->{'__$type'})) {
                $value = $this->newInstanceByType((array)$value->{'__$type'}, (array)$value);
            }

            if (!$prop || $prop['is_public']) {
                $object->$key = $value;
            } else {
                $property = new ReflectionProperty($this->className, $key);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            }
        }

        $this->snapshot($object, $document);

        return $object;
    }

    public function getId($object)
    {
        $id = $this->pProps[$this->idProperty];
        if ($id['is_public']) {
            return $object->{$id['phpProp']};
        }

        $property = new ReflectionProperty($this->className, $id['phpProp']);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Creates an snapshot of the current object.
     *
     * This function is called right after persisting any changes in the database or
     * when a new object is created.
     *
     * It creates a copy of the current data in order to optimise future persisting operations by
     * persisting only changes and not the whole document.
     *
     * @return void
     */
    public function snapshot($object, $data = null)
    {
        $data = $data ?: $this->toDocument($object);

        if (!$this->hasTrait) {
            $object->__lastInstance = $data;
            if (!empty($state['_id'])) {
                $object->__id = $state['_id'];
            }
        } else {
            $object->__setState($data);
        }
    }

    /**
     * Returns the last snapshoted state.
     *
     * @return array
     */
    protected function getLastState($object)
    {
        if ($this->hasTrait) {
            return $object->__getState();
        }

        return $object->__lastInstance;
    }

    /**
     * Returns the command for persisting the current object.
     *
     * This method returns the command that needs to be send to MongoDB to persist
     * a document.
     *
     * This method is also responsible for triggering all the `saving` events (before_save, after_save, etc).
     *
     * @TODO: This method should probably be in another class.
     *
     * @return array
     */
    public function getSaveCommand($object)
    {
        $document = [
            'connection' => $this->getCollection()->getDatabase()->getConnection(),
            'namespace'  => $this->getCollectionName(true),
            'collection' => $this->getCollectionName(),
        ];

        $prevDocument = $this->getLastState($object);

        $this->triggerEvent($object, 'before_save');
        if (!$prevDocument) {
            $this->triggerEvent($object, 'before_create');
            $document['command']  = 'create';
            $document['document'] = $this->toDocument($object, true, true);
        } else {
            $this->triggerEvent($object, 'before_update');
            $document['command'] = 'update';
            $diff = [];
            $new  = $this->toDocument($object);

            $diff = Update::diff($this->toDocument($object), $prevDocument);

            $document['selector'] = ['_id' => $prevDocument['_id']];
            $document['document'] = $diff;
        }

        return $document;
    }

    /**
     * Returns a document which represents the current object state.
     *
     * @param object $object
     * @param bool $validate    TRUE if validations should be enforced
     * @param bool $generateId  TRUE if a new ID should generated if the object does not have one
     *
     * @retun array
     */
    public function toDocument($object, $validate = true, $generateId = false)
    {
        if (!($object instanceof $this->className)) {
            throw new RuntimeException("Expecting an object of {$this->className}");
        }

        $keys = array_keys((array)$object);
        $keys = array_combine($keys, $keys);
        $array = [];

        foreach ($this->pProps as $key => $definition) {
            $mongo = $definition['mongoProp'];

            if ($definition['is_public']) {
                if (empty($keys[$key])) {
                    continue;
                }
                $php   = $definition['phpProp'];
                $value = $object->$php;
            } else {
                $property = new ReflectionProperty($this->className, $key);
                $property->setAccessible(true);
                $value = $property->getValue($object);
            }

            if (!$this->serializeValue($key, $value, $validate)) {
                continue;
            }

            if ($validate) {
                if (empty($value) && $definition['required']) {
                    throw new UnexpectedValueException("Unexpected empty value for property $key");
                } else if ($value && !empty($definition['validations'])) {
                    foreach ($definition['validations'] as $validation) {
                        if (is_array($validation[0])) {
                            list($class, $method) = $validation[0];
                            $response = $class::$method($value, $validation[1]);
                        } else if (is_callable($validation[0])) {
                            $response = $validation[0]($value, $validation[1]);
                        }

                        if (!$response) {
                            throw new UnexpectedValueException("Invalid value for $key ($value)");
                        }
                    }
                }
            }

            $array[$mongo] = $value;
        }

        foreach (get_object_vars($object) as $key => $value) {
            if (empty($this->pProps[$key]) && empty($this->mProps[$key])) {
                if (!$this->serializeValue($key, $value, $validate)) {
                    continue;
                }
                $array[$key] = $value;
            }
        }

        if (empty($array['_id']) && $generateId) {
            $array['_id'] = new ObjectID;
            $id = $this->pProps[$this->idProperty];
            if ($id['is_public']) {
                $object->{$this->idProperty} = $array['_id'];
            } else {
                $property = new ReflectionProperty($object, $this->idProperty);
                $property->setAccessible(true);
                $property->setValue($object, $array['_id']);
            }
        }

        return $array;
    }

}

