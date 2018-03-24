<?php

use Tuicha\Metadata;
use Docs\Doc1;

class ReferenceTest extends PHPUnit\Framework\TestCase
{
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
        $this->assertTrue(true); // test that no exception is thrown
    }

}
