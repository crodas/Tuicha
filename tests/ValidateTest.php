<?php

use Docs\Doc1;

class ValidateTest extends PHPUnit_Framework_TestCase
{
    /** @expectedException UnexpectedValueException */
    public function testRequiredAnnotation() {
        $d = new User;
        $d->save();
    }

    /** @expectedException UnexpectedValueException */
    public function testEmailValidation() {
        $d = new User;
        $d->name  = "xxx";
        $d->email = "lol";
        $d->save();
    }
}
