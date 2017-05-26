<?php

namespace Tuicha;

use Tuicha;
use Remember\Remember;
use RuntimeException;
use InvalidArgumentException;
use Datetime;
use UnexpectedValueException;
use MongoDB\BSON\UTCDateTime;
use Doctrine\Common\Inflector\Inflector;
use Notoj\ReflectionClass;
use Notoj\ReflectionProperty;
use Notoj\ReflectionMethod;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Type ;

/**
 *  Metadata
 */
class Metadata
{
    protected static $all_events = [
        'before_create' => ['before_create', 'beforeCreate'],
        'before_update' => ['before_update', 'beforeUpdate'],
        'before_save' => ['before_save', 'beforeSave'],
        'after_save' => ['after_save', 'afterSave'],
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
    protected $__connection;
    protected static $instances = [];

    final private function __construct($className)
    {
        $this->className = $className;
        $this->loadMetadata();
    }

    public function getConnection()
    {
        static $cache = [];
        if (empty($cache[$this->className])) {
            $connection = Tuicha::getConnection('default');
            $cache[$this->className] = [
                'dbName' => $connection['dbName'],
                'collection' => $connection['dbName'] . '.' . $this->getCollectionName(),
                'connection' => $connection['connection'],
            ];
        }

        return $cache[$this->className];
    }

    public function getCollectionName($dbName = false)
    {
        $collection = $this->collectionName;
        if ($dbName) {
            $connection = Tuicha::getConnection('default');
            $collection = $connection['dbName'] . '.' . $collection;
        }
        return $collection;
    }

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
                $value['__$type'] = compact('class');
            } 
        }

        return true;
    }

    protected function getAllArgument($annotations)
    {
        $arguments = [];
        foreach ($annotations as $annotation) {
            foreach ($annotation->getArgs() as $arg) {
                $arguments = array_merge($arguments, (array)$arg);
            }
        }

        $arguments = array_unique($arguments);

        foreach ($arguments as $id => $function) {
            if (is_callable(__NAMESPACE__ . '\Validation', $function)) {
                $arguments[$id] = [__NAMESPACE__ . '\Validation', $function];
            } else if (strpos($function, "::") > 0) {
                $arguments[$id] = explode("::", $function, 2);
            }
        }

        return $arguments;
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    protected function defineIndex(Array $index)
    {
        $name = [!empty($index['unique']) ? 'unique' : 'index'];
        foreach ($index['key'] as $field => $asc) {
            $name[] = $field . '_' . ($asc ? 'asc' : 'desc');
        }

        $index['name'] = implode('_', $name);
        $this->indexes[]= $index;
    }

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
            'validations' => $this->getAllArgument($annotations->get('validate')),
            'required'    => $annotations->has('required'),
            'is_public'   => $property->isPublic(),
            'is_private'  => $property->isPrivate(),
            'type'        => $annotations->has('type') ? $annotations->getOne('type')->getArgs() : NULL,
            'mongoProp'   => $mongoName,
            'phpProp'     => $phpName,
        ];

        $index = $annotations->getOne('index,unique');
        if ($index) {
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

        foreach ($annotations as $annotation) {
            $propData['annotations'][] = [$annotation->getName(), $annotation->getArgs()];
        }

        $this->pProps[$phpName] = $propData;
        $this->mProps[$mongoName] = $propData;
    }

    protected function processMethod(ReflectionMethod $method)
    {
        static $annMap = [];
        static $annotations = '';
        if (empty($annotations)) {
            foreach (self::$all_events as $event => $_annotations) {
                foreach ((array)$_annotations as $annotation) {
                    $annMap[strtolower($annotation)] = $event;
                }
            }
            $annotations = implode(",", array_keys($annMap));
        }
        if (!$method->getAnnotations()->has($annotations)) {
            return;
        }

        foreach ($method->getAnnotations()->get($annotations) as $annotation) {
            $event = $annMap[$annotation->getName()];
            $this->events[$event] = [
                'method' => $method->getName(),
                'is_public' => $method->isPublic(),
                'args' => $annotation->getArgs(),
            ];
        }
    }

    public function triggerEvent($object, $eventName)
    {
        if (empty($this->events[$eventName])) {
            return $this;
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

    protected function populateType($type, $value)
    {
        if (!empty($type['class'])) {
            return Metadata::of($type['class'])->newInstance($value);
        }

        return $value;
    }

    public function newInstance(array $data)
    {
        $class  = $this->className;
        $object = new $this->className;
        $state  = [];

        foreach ($data as $key => $value) {
            $prop = null;
            if (!empty($this->pProps[$key]) ||  !empty($this->mProps[$key])) {
                $prop = !empty($this->pProps[$key]) ? $this->pProps[$key] : $this->mProps[$key];
                $key  = $prop['phpProp'];
            }

            if (!empty($prop['type'])) {
                $value = $this->populateType($prop['type'], $value); 
            } else if (is_object($value) && !empty($value->{'__$type'})) {
                $value = $this->populateType((array)$value->{'__$type'}, (array)$value); 
            }

            if (!$prop || $prop['is_public']) {
                $object->$key = $value;
            } else {
                $property = new ReflectionProperty($this->className, $key);
                $property->setAccessible(true);
                $property->setValue($object, $value);
            }

            $state[$key] = $value;
        }

        $this->snapshot($object, $data);

        return $object;
    }


    protected function loadMetadata()
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

    public function getFile()
    {
        return $this->file;
    }

    public static function of($className)
    {
        static $loader;
        if (is_object($className)) {
            $className = get_class($className);
        }

        if (empty($loader)) {
            $loader = Remember::wrap('tuicha', function(&$args) {
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
    protected function getLastState($object)
    {
        if ($this->hasTrait) {
            return $object->__getState();
        }

        return $object->__lastInstance;
    }

    public function getSaveCommand($object)
    {
        if (!$this->__connection) {
            $this->__connection = Tuicha::getConnection('default');
            $this->__connection['namespace'] = $this->getCollectionName(true);
            $this->__connection['collection'] = $this->getCollectionName();
        }

        $document = $this->__connection;
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
                        if (is_array($validation)) {
                            list($class, $method) = $validation;
                            $response = $class::$method($value);
                        } else {
                            $response = $validation($value);
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
                $property->setAccesible(true);
                $property->setValue($object, $array['_id']);
            }
        }

        return $array;
    }

}

