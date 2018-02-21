<?php

use Tuicha\Metadata;
use Docs\Doc1;
use Docs\Doc3;
use MongoDB\BSON\ObjectID;

class foobar {}

class TestSave extends PHPUnit\Framework\TestCase
{
    public function testGenerateIdAndCreatedUpdated()
    {
        $x = new Doc3;
        $x->save();
        $this->assertTrue($x->id instanceof ObjectId);
        $this->assertTrue($x->updated_at instanceof \Datetime);
        $this->assertTrue($x->created_at instanceof \Datetime);

        $y = Doc3::find(['_id' => $x->id])->first();
        $this->assertEquals(
            $y->created_at->toDatetime()->format('Y-m-d H:i:s P'),
            $x->created_at->format('Y-m-d H:i:s P')
        );

        sleep(1);
        $x->save();
        $this->assertNotEquals(
            $y->updated_at->toDatetime()->format('Y-m-d H:i:s v P'),
            $x->updated_at->format('Y-m-d H:i:s v P')
        );
    }

    public function testSaveClassWithoutDeclaration()
    {
        $x = new Doc1;
        $x->f = new foobar;
        $x->f->lol = true;
        $x->save();

        $fromDb = Doc1::find(['_id' => $x->id])->first();
        $this->assertEquals(get_class($x->f), get_class($fromDb->f));
        unset($fromDb->f->{'__$type'});
        unset($fromDb->f->__lastInstance);
        $this->assertEquals($x->f, $fromDb->f);
    }

    public function testSaveArrayDiff()
    {
        $x = new Doc1;
        $x->x = [1, 2, 4, 5];
        $x->save();

        $x->x[] = 6;

        $update = Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals('update', $update['command']);
        unset($update['document']['$set']);
        $this->assertEquals(['$push' => [
            'x' => [
                '$each' => [6],
            ],
        ]], $update['document']);

        $x->x = [1, 2, 6, 7, 4];
        $update = Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals('update', $update['command']);
        $x->save();
        $this->assertEquals($x->x, Doc1::find(['_id' => $x->id])->first()->x);

        $x->x = fopen(__FILE__, 'r'); // It should be ignored
        $doc  = Metadata::of($x)->toDocument($x);
        $this->assertEquals(['_id'], array_keys($doc));
    }

    public function testSaveNestedObject()
    {
        $x = new Doc1;
        $x->x = [1, 2, 4];
        $x->save();

        $x->x[1] = (object)['foo' => 'bar'];

        $update = Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals('update', $update['command']);
        $this->assertEquals(['$set' => [
                'x.1' => (object)['foo' => 'bar'],
        ]], $update['document']);

        $x->save();
        $this->assertEquals($x->x, Doc1::find(['_id' => $x->id])->first()->x);
    }
    
}
