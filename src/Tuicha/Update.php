<?php

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
