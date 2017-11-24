<?php

use Tuicha\Util;

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
}
