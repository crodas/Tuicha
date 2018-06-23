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

namespace Tuicha;

use RuntimeException;
use MongoDB\BSON\Serializable;
use Tuicha;

/**
 * Reference class.
 *
 * This Reference class is the representation of a MongoDB reference.
 *
 * References in MongoDB has special keys (the collection name and the object
 * ID). This implementation would loads the real object when needed, in such
 * case this class is a Proxy class. All operations performed on it would
 * be reflected in the referenced document.
 *
 */
class Reference implements Serializable
{
    protected $ref;
    protected $id;
    protected $document;
    protected $cache = [];
    protected $properties;
    protected $readOnly;

    /**
     * Serializes the reference object.
     *
     * @return array
     */
    public function bsonSerialize()
    {
        $reference = ['$ref' => $this->ref, '$id' => $this->id];
        if (!empty($this->cache)) {
            $reference['__cache'] = $this->cache;
        }
        return $reference;
    }

    public function __construct(array $reference, $readOnly = false)
    {
        $this->ref   = $reference['$ref'];
        $this->id    = $reference['$id'];
        $this->cache = (array) (!empty($reference['__cache']) ? $reference['__cache'] : []);
        $metadata    = Metadata::ofCollection($this->ref);
        $this->properties = $metadata ? $metadata->getProperties() : [];
        $this->readOnly   = $readOnly;
    }

    /**
     * Loads and return the referenced object.
     *
     * If the document does not exists an exception will be thrown.
     *
     * @return object
     */
    public function getObject()
    {
        if (!$this->document) {
            $this->document = Tuicha::find($this->ref, ['_id' => $this->id])->first();
            if (!$this->document) {
                throw new RuntimeException("Cannot find object {$this->id} in collection {$this->ref}");
            }
        }
        return $this->document;
    }

    /**
     * Persist all the changes
     *
     * If the object has not been loaded this function does nothing.
     *
     * @return mixed
     */
    public function save()
    {
        if ($this->document && ! $this->readOnly && is_callable([$this->document, 'save'])) {
            return $this->document->save();
        }
    }

    /**
     * Executes a function in the referenced document
     *
     * @param string $name  Function name
     * @param array  $args  Arguments
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array([$this->getObject(), $name], $args);
    }

    /**
     * Sets a property
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->cache)) {
            $this->cache[$name] = $value;
        }
        $this->getObject()->$name = $value;
    }

    /**
     * Reads a property
     */
    public function __get($name)
    {
        $property= !empty($this->properties[$name]) ? $this->properties[$name] : null;
        if ($property && $property['type'] === 'id') {
            // there is no need to load the referenced object
            // to return its ID
            return $this->id;
        }

        if (!empty($this->cache[$name])) {
            return $this->cache[$name];
        }

        return $this->getObject()->$name;
    }
}
