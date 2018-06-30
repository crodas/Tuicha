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
        $x->yyyy = [1,[2,3]];
        $x->save();

        $x->x[1] = (object)['foo' => 'bar'];

        $update = Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals('update', $update['command']);
        $this->assertEquals(['$set' => [
            'x.1' => ['foo' => 'bar', '__class' => 'stdclass'],
            'user' => null,
        ]], $update['document']);

        $x->save();

        // add a new property in a nested object inside an array
        $x->x[1]->lol = 'lol';
        $update = Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals('update', $update['command']);
        $this->assertEquals(['$set' => [
            'x.1.lol' => 'lol',
            'user' => null,
        ]], $update['document']);
        $x->save();

        $y = Doc1::find(['_id' => $x->id])->first();
        $this->assertEquals($x->x, $y->x);

        // add a new property in a nested object inside an array
        $y->x[1]->xxx = 'xxx';
        $update = Metadata::of($y)->getSaveCommand($y);
        $this->assertEquals('update', $update['command']);
        $this->assertEquals(['$set' => [
            'x.1.xxx' => 'xxx',
            'user' => null,
        ]], $update['document']);
        $y->save();

        // unset a property in a nested object inside an array
        unset($y->x[1]->xxx);
        $update = Metadata::of($y)->getSaveCommand($y);
        $this->assertEquals('update', $update['command']);
        $this->assertEquals(['$set' => [
            'user' => null,
        ], '$unset' => [
            'x.1.xxx' => 1,
        ]], $update['document']);
        $y->save();
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
        foreach (Tuicha::find('users', [], 'default', true) as $user) {
            $this->assertTrue(is_array($user));
        }
    }
    
    public function testBugRecursiveInfiniteBug()
    {
        $x = new User;
        $x->name = uniqid(true);
        $x->email = uniqid(true) . '@foo.com';
        $x->save();

        $x->ref = new User;
        $x->ref->name  = uniqid(true);
        $x->ref->email = uniqid(true) . '@foo.com';
        $x->ref->ref = $x;

        $x->save();

        $this->assertEquals($x->ref->id, User::find(['id' => $x->ref->id])->first()->id);
        $this->assertEquals(Tuicha\Reference::class, get_class(User::find(['id' => $x->id])->first()->ref));

        $x = User::where('id', $x->id)->first();
        $x->ref->email = 'foo@xxx.net';
        $x->save();

        $user = User::find(['id' => $x->id])->first();
        $this->assertEquals($x->ref->email, $user->ref->email);

        $doc = new ReflectionProperty(Tuicha\Reference::class, 'document');
        $doc->setAccessible(true);
        $this->assertNull($doc->getValue($user->ref));
    }

    /**
     * @expectedException MongoDB\Driver\Exception\Exception
     */
    public function testSaveExceptionInvalidPropertyName()
    {
        $foo = new stdclass;
        $foo->{'$foo'} = 1;
        Tuicha::save($foo);
    }

    /**
     * @expectedException MongoDB\Driver\Exception\Exception
     */
    public function testDuplicateValueOnUniqueIndex()
    {
        $x = new User;
        $x->email = uniqid(true) . '@gmail.com';
        $x->name = uniqid(true);
        $x->save();

        $y = new User;
        $y->email = $x->email;
        $y->name = uniqid(true);
        $y->save();
    }

    public function testEmbedWithType()
    {
        $x = new User;
        $x->email = uniqid(true) . '@gmail.com';
        $x->karma = ['33'];
        $x->name  = uniqid(true);

        $y = new User;
        $y->email = uniqid(true) . '@gmail.com';
        $y->karma = [33.99];
        $y->name  = uniqid(true);
        $y->addAnotherUser($x);
        $y->save();

        $this->assertEquals(null, User::find(['email' => $x->email])->first());
        $this->assertEquals($x->email, User::find(['email' => $y->email])->first()->getAnotherUser()->email);
    }

    public function testSaveArrayByDefault()
    {
        $x = new User;
        $x->email = uniqid(true) . '@gmail.com';
        $x->karma = ['33'];
        $x->name  = uniqid(true);
        $x->random = ['foo' => 1, 'bar' => 2];
        $x->Save();

        $y = User::find(['id' => $x->id])->first();
        $this->assertTrue(is_array($y->random));
        $y->random = (object) $y->random;
        $y->save();

        $z = User::find(['id' => $x->id])->first();
        $this->assertFalse(is_array($z->random));

    }
}
