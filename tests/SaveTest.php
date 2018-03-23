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
        $this->assertEquals(['user', '_id'], array_keys($doc));
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
                'user' => null,
        ]], $update['document']);

        $x->save();
        $this->assertEquals($x->x, Doc1::find(['_id' => $x->id])->first()->x);
    }

    public function testSimpleReference()
    {
        $doc = new Doc1;
        $doc->x = 1;
        $doc->user = new User;
        $doc->user->name = 'foo';
        $doc->user->email = uniqid(true) . '@test.com';
        $doc->save();

        $doc2 = Doc1::find(['id' => $doc->id])->first();
        $this->assertEquals(Tuicha\Reference::class, get_class($doc2->user));
        $this->assertEquals(User::class, get_class($doc2->user->getObject()));
        $this->assertEquals($doc2->user->name, $doc2->user->getObject()->name);
    }

    public function testReferenceUpdate()
    {
        $doc = new Doc1;
        $doc->x = 1;
        $doc->user = new User;
        $doc->user->name = 'foo';
        $doc->user->email = uniqid(true) . '@test.com';
        $doc->save();

        $doc2 = Doc1::find(['id' => $doc->id])->first();
        $doc2->user->xyxy = 'lol';
        $doc2->save();

        $this->assertEquals('lol', User::find(['id' => $doc->user->id])->first()->xyxy);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testReferenceMissingDocument()
    {
        $x = new stdclass;
        $x->id = 99;
        Tuicha::makeReference($x)->getObject();
    }

    public function testReferenceSaveNoChanges()
    {
        $x = new stdclass;
        $x->id = 99;
        Tuicha::makeReference($x)->save();
    }

    public function testSaveSerializableInterface()
    {
        $x = new stdclass;
        $x->id = 99;
        $ref = Tuicha::makeReference($x);
        $this->assertEquals($ref->bsonSerialize(), Metadata::of($ref)->toDocument($ref));
    }

    public function testRawQuery()
    {
        foreach (Tuicha::find('users', [], [], 'default', true) as $user) {
            $this->assertTrue(is_array($user));
        }
    }
    
}
