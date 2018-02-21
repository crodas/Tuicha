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
                $q->or(function($q) {
                    $q->foo = 'bar';
                });
            })->set(function($s) {
                $s->foo->unset();
                $s->bar->remove();
            });

        $this->assertEquals(['$or' => [['foo' => 'bar']]], $query->getFilter());
        $this->assertEquals(['$unset' => ['foo' => '', 'bar' => '']], $query->getUpdateDocument());
        $result = $query->execute();
    }

    public function testOptions()
    {
        $x = Doc1::update();
        $this->assertEquals(['wait' => true, 'upsert' => false, 'multi' => true], $x->getOptions());

        $x->multi(false);
        $x->wait(false);
        $x->upsert(true);
        $this->assertEquals(['wait' => false, 'upsert' => true, 'multi' => false], $x->getOptions());
    }

    public function testDelete()
    {
        $x = new Doc1;
        $x->save();
        $this->assertNotEquals(0, Doc1::count());

        Doc1::delete()->execute();

        $this->assertEquals(0, Doc1::count());
    }

    public function testDeleteTuicha()
    {
        $x = new Doc1;
        $x->save();

        $this->assertNotEquals(0, Doc1::count());

        Tuicha::delete('Docs\Doc1')->execute();

        $this->assertEquals(0, Doc1::count());
    }
}
