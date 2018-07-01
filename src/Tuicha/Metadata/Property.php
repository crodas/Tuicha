<?php

namespace Tuicha\Metadata;

use Notoj\Annotation\Annotation;
use Notoj\Annotation\Annotations;
use Notoj\ReflectionProperty;
use RuntimeException;

class Property
{
    protected $phpName;
    protected $mongoName;
    protected $annotations = [];
    protected $validations = [];
    protected $type;
    protected $required = false;
    protected $isPublic = true;

    public function __construct($phpName, $mongoName = null, ReflectionProperty $reflection = null)
    {
        $this->phpName   = $phpName;
        $this->mongoName = $mongoName ?: $phpName;
        $this->type      = $this->type ?: new DataType($mongoName === '_id' ? 'id' : '');

        if ($reflection) {
            $annotations = $reflection->getAnnotations();
            $this->phpName   = $reflection->getName();
            $this->mongoName = $annotations->has('field') ? $annotations->getOne('field')->getArg(0) : $this->phpName;
            if ($annotations->has('id')) {
                $this->mongoName = '_id';
            }

            $this->parseReflection($reflection, $annotations);
        }
    }

    public function parseReflection(ReflectionProperty $reflection, Annotations $annotations)
    {

        $this->isPublic    = $reflection->isPublic();
        $this->required    = $annotations->has('required');
        $this->validations = $this->getAnnotationArguments($annotations->get('validate'));
        $this->type        = $this->parseDataType($annotations);
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
    protected function parseDataType(Annotations $annotations)
    {
        $types = 'int,integer,float,double,array,bool,boolean,string,object,class,type,id,reference';
        if ($annotation = $annotations->getOne($types)) {
            return $this->getDataTypeFromAnnotation($annotation);
        }

        return $this->mongoName === '_id' ?  new DataType('id') : $this->type;
    }

    public function mongo()
    {
        return $this->mongoName;
    }

    public function php()
    {
        return $this->phpName;
    }

    public function isPublic()
    {
        return $this->isPublic;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(DataType $type)
    {
        $this->type = $type;
        return $this;
    }

    public function getValue($object)
    {
        if ($this->isPublic) {
            return $object->{$this->phpName};
        }

        $reflection = new ReflectionProperty($object, $this->phpName);
        $reflection->setAccessible(true);
        return $reflection->getValue($object);
    }

    public function setValue($object, $value)
    {
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
        if (empty($value) && $definition['required']) {
            throw new UnexpectedValueException("Unexpected empty value for property {$this->phpName}");
        } else if ($value && !empty($definition['validations'])) {
            foreach ($definition['validations'] as $validation) {
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
