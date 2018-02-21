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
namespace Tuicha\Fluent;

use RuntimeException;

trait Filter
{
    protected $filter = [];
    protected static $math = [
        '>=' => '$gte',
        '>'  => '$gt',
        '<=' => '$lte',
        '<'  => '$lt',
        '!=' => '$ne',
        '<>' => '$ne',
        '==' => '$eq',
        '='  => '$q',
    ];



    /**
     * Returns the current filter array
     *
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    public function offsetUnset($offset)
    {
        unset($this->filter[$offset]);
    }

    public function offsetSet($property, $value)
    {
        return $this->where($property, $value);
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->filter);
    }

    public function offsetGet($offset)
    {
        return new Property($this, $offset);
    }

    public function __get($property)
    {
        return new Property($this, $property);
    }


    /**
     * Query functions
     */

    /**
     * Where
     *
     * Adds a where clause to the current query
     *
     * If the $op value is optional, its default value is `$eq`.
     *
     * @param string $property  Property name.
     * @param string $op        Operation.
     * @param mixed  $value     Value to compare against.
     *
     * @return $this
     */
    public function where()
    {
        $arguments = func_get_args();
        if (empty($arguments)) {
            throw new RuntimeException("where() must have at least one argument");
        }

        $property = $arguments[0];

        switch (count($arguments)) {
        case 1:
            // One argument
            if (is_callable($property)) {
                // It's a function
                $property($this);
                return $this;
            } else if (is_scalar($property)) {
                // It is a property name
                return new Property($this, $property);
            }

            // It's a MongoDB raw query
            $this->filter = array_merge($this->filter, $property);

            return $this;

        case 2:
            // Two arguments, name and value
            $op    = '$eq';
            $value = $arguments[1];
            break;

        case 3:
            // Three arguments, name, operation and value.
            $op    = $arguments[1];
            $value = $arguments[2];
            break;
        }

        if (!empty($this->filter[$property]) && is_array($this->filter[$property])) {
           $this->filter[$property][$op] = $value;
           return $this;
        }

        $op = strtolower($op);
        if (!empty(self::$math[$op])) {
            $op = self::$math[$op];
        } else if ($op[0] !== '$') {
            $op = '$' . $op;
        }

        if ($op != '$eq') {
            $value = [$op => $value];
        }

        $this->filter[$property] = $value;

        return $this;
    }

    /**
     * Equal
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/eq/#op._S_eq
     *
     * @return $this
     */
    public function __set($property, $value)
    {
        return $this->where($property, $value);
    }

    /**
     * Equal
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/eq/#op._S_eq
     *
     * @return $this
     */
    public function is($property, $value)
    {
        return $this->where($property, $value);
    }

    /**
     * Not equal.
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/ne/#op._S_ne
     *
     * @return $this
     */
    public function isNot($property, $value)
    {
        return $this->where($property, '$ne', $value);
    }

    /**
     * Greater than
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/gt/#op._S_gt
     *
     * @return $this
     */
    public function gt($property, $value)
    {
        return $this->where($property, '$gt', $value);
    }

    /**
     * Greater than or Equal
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/gte/#op._S_gte
     *
     * @return $this
     */
    public function gte($property, $value)
    {
        return $this->where($property, '$gte', $value);
    }

    /**
     * Lower than
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/lt/#op._S_lt
     *
     * @return $this
     */
    public function lt($property, $value)
    {
        return $this->where($property, '$lt', $value);
    }

    /**
     * Lower than or equal
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/lte/#op._S_lte
     *
     * @return $this
     */
    public function lte($property, $value)
    {
        return $this->where($property, '$lte', $value);
    }

    /**
     * If a property is between a $min and a $max number.
     *
     * Internally this function is a combination of $lte and $gte.
     *
     * @param string $property  Property name
     * @param mixed  $value     Value
     *
     * @return $this
     */
    public function between($property, $min, $max)
    {
        return $this->where($property, '$gte', $min)
            ->where($property, '$lte', $max);
    }

    /**
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/type/#type
     */
    public function type($property, $value)
    {
        return $this->where($property, '$type', $value);
    }

    /**
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/exists/#op._S_exists
     */
    public function exists($property, $value = true)
    {
        return $this->where($property, '$exists', (bool)$value);
    }

    /**
     * All
     *
     * Query to check if a property's value is an array and it has
     * all the same values as in $array.
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/all/#op._S_all
     *
     * @param string $property  Property name
     * @param array  $array     Value
     *
     * @return $this
     */
    public function all($property, array $array)
    {
        return $this->where($property, '$all', $array);
    }

    /**
     * In
     *
     * Query to check if a property's value is one of the possible
     * values defined in $array.
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/in/#op._S_in
     *
     * @param string $property  Property name
     * @param array  $array     Value
     *
     * @return $this
     */
    public function in($property, array $array)
    {
        return $this->where($property, '$in', $array);
    }

    /**
     * Not In
     *
     * Query to check if a property's value is not any of the values
     * defined in $array.
     *
     * @link https://docs.mongodb.com/manual/reference/operator/query/nin/#op._S_nin
     *
     * @param string $property  Property name
     * @param array  $array     Value
     *
     * @return $this
     */
    public function notIn($property, array $array)
    {
        return $this->where($property, '$nin', $array);
    }

    /**
     * Size of an array
     *
     * @param string $property  Property name
     * @param array  $size     Value
     *
     * @return $this
     */
    public function size($property, $size)
    {
        return $this->where($property, '$size', $size);
    }


    /**
     * Magic methods
     */

    /**
     * Dynamic function handler
     *
     * This is a wrap over `and` and `or` to avoid parsing errors
     * on older PHP.
     *
     * @param string $name          Function name
     * @param array  $arguments     List of arguments
     */
    public function __call($name, $arguments)
    {
        switch (strtolower($name)) {
        case 'and':
            return $this->expressions('$and', $arguments);
        case 'or':
            return $this->expressions('$or', $arguments);
        }

        throw new RuntimeException("$name is not a valid function");
    }

    /**
     * 'nor' logic for expressions
     *
     * This function expects functions. Each function is treated as a
     * an expression with the 'NOR' operation.
     *
     * @return $this
     */
    public function nor()
    {
        return $this->expressions('$nor', func_get_args());
    }

    /**
     * 'nor' logic for expressions
     *
     * This function expects functions. Each function is treated as a
     * an expression with the 'NOR' operation.
     *
     * @return $this
     */
    public function nor_()
    {
        return $this->expressions('$nor', func_get_args());
    }

    /**
     * 'and' logic for expressions
     *
     * This function expects functions. Each function is treated as a
     * an expression with the 'AND' operation.
     *
     * @return $this
     */
    public function and_()
    {
        return $this->expressions('$and', func_get_args());
    }

    /**
     * 'or' logic for expressions
     *
     * This function expects functions. Each function is treated as a
     * an expression with the 'OR' operation.
     *
     * @return $this
     */
    public function or_()
    {
        return $this->expressions('$or', func_get_args());
    }

    /**
     * Not - Negates an expression
     *
     * Negates an expression, the expression is a callback.
     *
     * @return $this
     */
    public function not(Callable $callback)
    {
        $filter = $this->filter;
        $this->filter = [];

        $callback($this);

        $tmpfilter    = $this->filter;
        $this->filter = $filter;

        if (!empty($tmpfilter)) {
            foreach ($tmpfilter as $key => $value) {
                $this->where($key, 'not', $value);
            }
        }

        return $this;
    }

    /**
     * Expressions
     *
     * This functions handles defining expressions easily.
     *
     * In order to keep the interface as friendly as possible this function accepts an $operation
     * ('and', 'or' or 'nor') and a set of function. Each function will have their own scope
     * to add their own filters, their own expression. These expressions are later concatenated
     * with the boolean $operation.
     *
     * @param string $operation     $and|$or|$nor
     * @param array  $callbacks     array of callbacks
     *
     * @return $this
     */
    protected function expressions($operation, Array $callbacks)
    {
        $filter = $this->filter;
        foreach ($callbacks as $callback) {
            $this->filter = [];
            $callback($this);
            if (!empty($this->filter)) {
                $filter[$operation][] = $this->filter;
            }
        }

        $this->filter = $filter;

        return $this;
    }
}
