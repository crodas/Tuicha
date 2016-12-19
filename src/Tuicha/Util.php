<?php

namespace Tuicha;

class Util
{
    public static function isArray()
    {
        foreach (func_get_args() as $argument) {
            if (!is_array($argument) || array_values($argument) !== $argument) {
                return false;
            }
        }

        return true;
    }

    public static function arrayDiff(array $new, array $old)
    {
        $newLength = count($new);
        $oldLength = count($old);
        $cLength   = min($newLength, $oldLength);

        $add    = [];
        $update = [];
        $remove = [];

        for ($i = 0; $i < $cLength; ++$i) {
            if ($new[$i] !== $old[$i]) {
                $update[$i] = $new[$i];
            } 
        }

        if ($newLength < $oldLength) {
            $remove = array_slice($old, $cLength);
        } else {
            $add = array_slice($new, $cLength);
        }

        return compact('add', 'update', 'remove');
    }
}
