<?php

$counter = 0;

class ObserverClass
{
    public function creating(User $user)
    {
        global $counter;
        ++$counter;
        $user->prop = 1;
    }
}

class EventTest extends PHPUnit\Framework\TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function testRegisterWrongClass()
    {
        User::observe(NonExistenClass::class);
    }

    public function testRegisterObserver()
    {
        global $counter;
        User::observe(ObserverClass::class);
        $this->assertEquals(0, $counter);
        $x = new User;
        $x->name  = uniqid(true);
        $x->email = 'foo@gmail.com';
        $x->save();
        $this->assertEquals(1, $counter);
        $this->assertEquals(1, User::firstOrFail($x->id)->prop);
    }
}
