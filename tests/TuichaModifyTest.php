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

    public function testUpdateRename()
    {
        $query = Doc1::update()
            ->where(function($q) {
                $q->foo = 'bar';
            })->set(function($s) {
                $s->foo->rename('bar');
            });

        $this->assertEquals(['foo' => 'bar'], $query->getFilter());
        $this->assertEquals(['$rename' => ['foo' => 'bar']], $query->getUpdateDocument());
    }

    public function testUpdateUnset()
    {
        $query = Doc1::update()
            ->where(function($q) {
                $q->foo = 'bar';
            })->set(function($s) {
                $s->foo->unset();
                $s->bar->unset();
            });

        $this->assertEquals(['foo' => 'bar'], $query->getFilter());
        $this->assertEquals(['$unset' => ['foo' => '', 'bar' => '']], $query->getUpdateDocument());
    }
}
