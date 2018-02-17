<?php

use Docs\Doc1;

class TuichaModifyTest extends PHPUnit\Framework\TestCase
{
    public function testUpdate()
    {
        $x = new Doc1;
        $x->foo = 'bar';
        $x->lol = 1;
        $x->num = 5;
        $x->save();

        Doc1::update(['_id' => $x->id])
            ->set(function($f) {
                $f->lol->add(5);
                $f->num->multiply(5);
                $f->updated->now();
            })->execute(true);

        $updated = Doc1::find(['_id' => $x->id])->first();
        $this->assertEquals(6, $updated->lol);
        $this->assertEquals(25, $updated->num);
        $this->assertEquals('MongoDB\BSON\UTCDateTime', get_class($updated->updated));
    }
}
