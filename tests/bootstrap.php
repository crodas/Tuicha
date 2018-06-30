<?php

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ERROR | E_WARNING | E_PARSE);

Remember\Remember::setDirectory(__DIR__ . '/tmp');

Tuicha::addConnection("tuicha_testsuite");
Tuicha::addDirectory(__DIR__ . '/docs');

Tuicha::dropDatabase();

class CustomValidator
{
    public static function isInt($val)
    {
        return is_int($val);
    }
}
