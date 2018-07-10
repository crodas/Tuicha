<?php

namespace Tuicha\Metadata;

use Notoj\Annotation\Annotation;
use Notoj\Annotation\Annotations;
use Notoj\ReflectionProperty;
use Tuicha\Validation;
use UnexpectedValueException;
use RuntimeException;

class Property
{
    protected $phpName;
    protected $mongoName;
    protected $isDefined = false;
    protected $annotations;
    protected $metadata;
    protected $validations = [];
    protected $type;
    protected $required = false;
    protected $isPublic = true;

    public function __construct($metadata, $phpName, $mongoName = null, ReflectionProperty $reflection = null)
    {
        $this->metadata  = $metadata;
        $this->phpName   = $phpName;
        $this->mongoName = $mongoName ?: $phpName;
        $this->type      = $this->type ?: new DataType($mongoName === '_id' ? 'id' : '');

        if ($reflection) {
            $this->isDefined   = true;
            $this->annotations = $reflection->getAnnotations();
            $this->phpName     = $reflection->getName();
            $this->mongoName   = $this->annotations->has('field') ? $this->annotations->getOne('field')->getArg(0) : $this->phpName;
            if ($this->annotations->has('id')) {
                $this->mongoName = '_id';
            }

            $this->parseReflection($reflection);
        } else {
            // create empty annotation object
            $this->annotations = new Annotations;
        }
    }

    /**
     * Returns all the arguments from an array of annotations
     *
     * @param array $annotations An array of Notoj\Annotation\Annotation objects
     *
     * @return array
     */
    protected function getAnnotationArguments(array $annotations)
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

            if (is_callable([Validation::class, $function])) {
                $function = [Validation::class, $function];
            } else if (is_string($function) && strpos($function, "::") > 0) {
                $function = explode("::", $function, 2);
            }
            $arguments[$id] = [$function, $args];
        }

        return $arguments;
    }

    /**
     * Get the type definition from a single Annotation.
     *
     * @return array
     */
    protected function getDataTypeFromAnnotation(Annotation $annotation)
    {
        $type = new DataType($annotation->getName());

        switch ($annotation->getName()) {
        case 'class':
            $type->addData('class', strtolower($annotation->getArg()));
            break;
        case 'array':
            try {
                $type->addData('element', $this->getDataTypeFromAnnotation($annotation->getArg()));
            } catch (RuntimeException $e) {
            }
            break;
        case 'reference':
            foreach ($annotation->getArgs() as $name => $value) {
                $type->addData($name, $value);
            }
            break;
        }

        return $type;
    }

    /**
     * Returns the data type definition for a property
     *
     * @param Annotations $annotations  Property's annotations object
     *
     * @return array
     */
    protected function parseDataType()
    {
        $types = 'int,integer,float,double,array,bool,boolean,string,object,class,type,id,reference';
        if ($annotation = $this->annotations->getOne($types)) {
            return $this->getDataTypeFromAnnotation($annotation);
        }

        return $this->mongoName === '_id' ?  new DataType('id') : $this->type;
    }

    /**
     * __sleep()
     *
     * Serializes the annotations property to speedup the wakeup process. The annotations
     * is unserialized on demand by the `getAnnotations()` method
     *
     * @return array
     */
    public function __sleep()
    {
        if (!is_string($this->annotations)) {
            $this->annotations = serialize($this->annotations);
        }

        return array_keys((array)$this);
    }

    /**
     * Parses and extract all the property information from the reflection and annotations
     */
    public function parseReflection(ReflectionProperty $reflection)
    {

        $this->isPublic    = $reflection->isPublic();
        $this->required    = $this->annotations->has('required');
        $this->validations = $this->getAnnotationArguments($this->annotations->get('validate'));
        $this->type        = $this->parseDataType();
    }

    /**
     * Returns the Mongo name of this property
     *
     * @return string
     */
    public function mongo()
    {
        return $this->mongoName;
    }

    /**
     * Returns the PHP name of this property
     *
     * @return string
     */
    public function php()
    {
        return $this->phpName;
    }

    /**
     * Returns if this property is public or not.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->isPublic;
    }

    /**
     * Returns the annotations object
     *
     * @return Notoj\Annotation\Annotations
     */
    public function getAnnotations()
    {
        if (is_string($this->annotations)) {
            $this->annotations = unserialize($this->annotations);
        }
        return $this->annotations;
    }

    /**
     * Returns the DataType of the property
     *
     * @return Tuicha\Metadata\DataType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Changes the DataType of this property
     *
     * @param Tuicha\Metadata\DataType $type
     *
     * @return $this
     */
    public function setType(DataType $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get the property value of a given object
     *
     * @param $object   Object to get the value from
     *
     * @return mixed
     */
    public function getValue(object $object)
    {
        $this->metadata->ensureObjectType($object);
        if ($this->isPublic) {
            if ($this->isDefined || array_key_exists($this->phpName, (array)$object)) {
                return $object->{$this->phpName};
            }

            return null;
        }

        $reflection = new ReflectionProperty($object, $this->phpName);
        $reflection->setAccessible(true);
        return $reflection->getValue($object);
    }

    /**
     * Checks if a given object has a property
     *
     * @param $object   Object to get the value from
     *
     * @return bool
     */
    public function hasValue(object $object)
    {
        $this->metadata->ensureObjectType($object);
        return ! $this->isPublic || $this->isDefined || array_key_exists($this->phpName, (array)$object);
    }

    /**
     * Sets a value on the property of a given object
     *
     * @param $object   Object to set the property
     * @param $value    Value to set
     */
    public function setValue($object, $value)
    {
        $this->metadata->ensureObjectType($object);
        if ($this->isPublic) {
            $object->{$this->phpName} = $value;
        } else {
            $property = new ReflectionProperty($object, $this->phpName);
            $property->setAccessible(true);
            $property->setValue($object, $value);
        }
    }

    /**
     * Validates a property's value
     *
     * @param mixed  $value         Property value
     *
     * @return mixed
     */
    public function validate($value)
    {
        if (empty($value) && $this->required) {
            throw new UnexpectedValueException("Unexpected empty value for property {$this->phpName}");
        } else if ($value && !empty($this->validations)) {
            foreach ($this->validations as $validation) {
                $response = true;
                if (is_array($validation[0])) {
                    list($class, $method) = $validation[0];
                    $response = $class::$method($value, $validation[1]);
                } else if (is_callable($validation[0])) {
                    $response = $validation[0]($value, $validation[1]);
                }

                if (!$response) {
                    throw new UnexpectedValueException("Invalid value for {$this->phpName} ($value)");
                }
            }
        }

        return $value;
    }

}
