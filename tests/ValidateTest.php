<?php

use Docs\Doc1;

class ValidateTest extends PHPUnit\Framework\TestCase
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
    
    /** @expectedException UnexpectedValueException */
    public function testIsIntegerValidation() {
        $d = new User;
        $d->name  = "xxx";
        $d->email = "lol@lol.com";
        $d->age   = 'foobar';
        $d->save();
    }

    public function testIsIntegerValidationFlotToInt() {
        $d = new User;
        $d->name  = "xxx";
        $d->email = "lol@lol.com";
        $d->age   = 19.2;
        $d->save();

        $this->assertEquals(19, User::find(['_id' => $d->id])->first()->age);
        $this->assertEquals(19, $d->age);

    }
}
