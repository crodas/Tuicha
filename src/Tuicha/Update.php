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

class Update
{
    protected static function calculateDifferences($property, $new, $old)
    {
        $changes = [];
        if (Util::isArray($old, $new)) {
            $diff = Util::arrayDiff($new, $old);

            if (!empty($diff['add'])) {
                $changes[] = ['$push', $property, ['$each' => array_values($diff['add'])]];
            }

            if (!empty($diff['update'])) {
                foreach ($diff['update'] as $id => $value) {
                    $changes[] = ['$set', $property . '.' . $id, $value];
                }
            }

            if (!empty($diff['remove'])) {
                $changes[] = ['$pullAll', $property, array_values($diff['remove'])];
            }

        }

        return $changes ?: [['$set', $property, $new]];
    }

    public static function diff(array $new, array $prevDocument)
    {
        $diff = [];
        foreach ($new as $key => $value) {
            if (empty($prevDocument[$key])) {
                $diff['$set'][$key] = $value;
            } elseif ($value !== $prevDocument[$key]) {
                foreach (self::calculateDifferences($key, $value, $prevDocument[$key]) as $operation) {
                    list($op, $property, $update) = $operation;
                    $diff[$op][$property] = $update;
                }
            }
        }

        return $diff;
    }
}
