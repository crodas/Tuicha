<?php

use Tuicha\Util;
use Tuicha\Update;

class UtilTest extends PHPUnit\Framework\TestCase
{
    public function testIsArray()
    {
        $this->assertTrue(Util::isArray([1,2,3,4,5], [99,24,343]));
        $this->assertFalse(Util::isArray([1,2,3,4,5], [99,24,343], [1,2,3,4,5, 'foo' => 'bar']));
    }

    public function testArrayDiff()
    {
        $old = [1, 2, 3];
        $new = [1, 4, 3, 9];
        $this->assertEquals([
            'add' => [9],
            'update' => [1 => 4],
            'remove' => [],
        ], Util::arrayDiff($new, $old));

        $this->assertEquals([
            'remove' => [9],
            'update' => [1 => 2],
            'add' => [],
        ], Util::arrayDiff($old, $new));
    }

    public function testArrayDiffToQuery()
    {
        $old  = [1, 2, 3];
        $new  = [1, 4, 9, 129, 133];
        $diff = Update::diff(['x' => $old], ['x' => $new]);

        $this->assertEquals($diff['$set'],  ['x.1' => 2, 'x.2' => 3]);
        $this->assertEquals($diff['$pullAll'],  ['x' => [129, 133]]);
    }
}
