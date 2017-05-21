<?php

use Tuicha\Util;

class UtilTest extends PHPUnit\Framework\TestCase
{
    function testIsArray()
    {
        $this->assertTrue(Util::isArray([1,2,3,4,5], [99,24,343]));
        $this->assertFalse(Util::isArray([1,2,3,4,5], [99,24,343], [1,2,3,4,5, 'foo' => 'bar']));
    }
}
