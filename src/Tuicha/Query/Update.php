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
namespace Tuicha\Query;

use MongoDB\Driver\WriteConcern;
use Tuicha;

/**
 * Update class
 *
 * This class abstracts the update operations. It uses the same fluent interface as
 * in the Query and Delete classes.
 *
 * Updates are a bit different though, aside from filtering it also supports updates
 * operations on fields.
 *
 * To differentiate both stages this class has the isUpdating flag. If it is false (default)
 * it would queue any property operation into the `filter` property, otherwise it would be
 * queued into the `update` property.
 */
class Update extends Modify
{
    protected $update = [];
    protected $options = [
        'wait' => true,
        'multi' => true,
        'upsert' => false,
    ];
    protected $isUpdating = false;

    /**
     * Toggle on or off the multi option
     *
     * The multi option tells the engine to modify at most one document if it is OFF,
     * otherwise it will modify all the documents that matches the filtering criteria.
     *
     * @param bool $multi
     *
     * @return $this
     */
    public function upsert($upsert = true)
    {
        $this->options['upsert'] = (bool) $upsert;
        return $this;
    }

    /**
     *
     */
    public function __set($name, $value)
    {
        if (!$this->isUpdating) {
            return parent::__set($name, $value);
        }

        $this->update['$set'][$name] = $value;

        return $this;
    }

    /**
     * Sets the value of a property to current date, either as a Date or a Timestamp.
     *
     * @link https://docs.mongodb.com/manual/reference/operator/update/currentDate/#up._S_currentDate
     *
     * @param string $property  Property name
     * @param string $type      'date' OR 'timestamp'
     *
     * @return $this
     */
    public function now($property, $type = 'date')
    {
        $this->update['$currentDate'][$property] = ['$type' => $type];
        return $this;
    }

    /**
     * Increments the value of the property by the specified amount.
     *
     * @param string $property  Property name
     * @param float|int $value  Number to increment.
     *
     * @return $this
     */
    public function add($name, $value)
    {
        $this->update['$inc'][$name] = $value;
        return $this;
    }

    /**
     * Multiplies the value of the property by the specified amount.
     *
     * @param string $property  Property name
     * @param float|int $value  Number to increment.
     *
     * @return $this
     */
    public function multiply($name, $value)
    {
        $this->update['$mul'][$name] = $value;
    }

    /**
     * Renames a property
     *
     * @param string $old       Current property name
     * @param string $new       New Property name
     *
     * @return $this
     */
    public function rename($old, $new)
    {
        $this->update['$rename'][$old] = $new;
        return $this;
    }

    /**
     * Removes the specified property from a document.
     *
     * This function receives many arguments, each argument is a property name.
     *
     * @return $this
     */
    public function unset()
    {
        foreach (func_get_args() as $property) {
            $this->update['$unset'][$property] = '';
        }
        return $this;
    }

    /**
     * Update
     *
     * If the argument is callable it enables the "update" mode, so any property
     * returned by __set() would perform update operations rather than "filtering".
     *
     * If the argument is not callable it is expected to be an array, and it would be merged
     * with the update operations.
     *
     * @param mixed $expr
     *
     * @return $this
     */
    public function update($expr)
    {
        if (is_callable($expr)) {
            $this->isUpdating = true;
            $expr($this);
            $this->isUpdating = false;
            return $this;
        }

        $this->update = array_merge($this->update, $expr);
        return $this;
    }

    /**
     * @aliasof update()
     */
    public function set($expr)
    {
        return $this->update($expr);
    }

    /**
     * Returns the update operations
     *
     * @return array
     */
    public function getUpdateDocument()
    {
        return $this->update;
    }

    /**
     * Executes the operation.
     *
     * @param bool $wait    If null it's ignored and the class variable is used
     * @param bool $multi   If null it's ignored and the class variable is used
     * @param bool $upsert  If null it's ignored and the class variable is used
     */
    public function execute($wait = null, $multi = null, $upsert = null)
    {
        $options = array_merge($this->options, array_filter(compact('wait', 'multi', 'upsert'), 'is_bool'));

        $wConcern = new WriteConcern($options['wait'] ? WriteConcern::MAJORITY : '');
        $updates  = [];

        // Each operation goes on its own update. By doing so
        // Tuicha supports multiple updates to same properties
        foreach ($this->update as $operation => $values) {
            $updates[] = [
                'q' => (object)$this->filter,
                'u' => [$operation => $values],
                'upsert' => $options['upsert'],
                'multi'  => $options['multi'],
            ];
        }

        return Tuicha::command([
            'update' => $this->collection,
            'updates' => $updates,
            'ordered' => true,
            'writeConcern' => $wConcern,
        ]);
    }

}
