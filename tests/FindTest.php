<?php

use Docs\Doc1;
use Docs\Doc4;
use Docs\DocWithoutTrait;

class FindTest extends PHPUnit\Framework\TestCase
{
    public function testFindOrCreate()
    {
        $where = ['email' => uniqid() . '@name.com', 'name' => 'lol'];
        $user1 = User::firstOrCreate($where);
        $this->assertEquals($where['email'], $user1->email);
        $this->assertEquals($user1->id, User::firstOrCreate($where)->id);
    }

    public function testCollectionUpdate()
    {
        $where = ['email' => uniqid() . '@name.com', 'name' => 'lol'];
        $user1 = User::firstOrCreate($where);
        $this->assertEquals($where['email'], $user1->email);
        $this->assertTrue(empty($user1->foo));

        $ret = User::update($where, ['$set' => ['foo' => 'bar']])
            ->execute(true);

        $user2 = User::firstOrCreate($where);
        $this->assertEquals('bar', $user2->foo);
    }

    public function testDocumentNoTrait()
    {
        $x = new DocWithoutTrait;
        $x->foo = 'bar';
        Tuicha::save($x);

        Tuicha::update('Docs\DocWithoutTrait')
            ->where(function($query) {
                $query->foo = 'bar';
            })
            ->set(function($update) {
                $update->foo = 'xxx';
            })->execute(true);

        $x = Tuicha::find('Docs\DocWithoutTrait')
            ->foo->is('bar')
            ->first();
        $this->assertEquals(null, $x);

        $x = Tuicha::find('Docs\DocWithoutTrait')
            ->foo->is('xxx')
            ->first();
        $this->assertEquals('xxx', $x->foo);

        $x->lol = 1;

        $update = Tuicha\Metadata::of($x)->getSaveCommand($x);
        $this->assertEquals(['$set' => ['lol' => 1]], $update['document']);
    }

    public function testCreateIndex()
    {
        $this->assertTrue(User::createIndex() instanceof MongoDB\Driver\Cursor);
    }

    public function testCountZero()
    {
        $this->assertEquals(0, Doc1::count());
    }

    /**
     *  @dependsOn testCountZero
     */
    public function testCreate()
    {
        $foo = new Doc1;
        $foo->bar = 'lol';
        $foo->save();

        $this->assertEquals(1, Doc1::count());
    }

    /**
     *  @dependsOn testCreate
     */
    public function testFindOneNoArgument()
    {
        $doc = Doc1::find()->first();
        $this->assertTrue($doc instanceof Doc1);
        $this->assertEquals('lol', $doc->bar);
    }

    /**
     *  @dependsOn testFindOneNoResult
     */
    public function testFindOneWithResult()
    {
        $doc = Doc1::find(function($q) {
            $q->bar = uniqid('', true);
        })->first();
        $this->assertNull($doc);
        $doc = Doc1::find(function($q) {
            $q->bar = 'lol';
        })->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc instanceof Doc1);
        $this->assertEquals('lol', $doc->bar);
    }

    /**
     *  @dependsOn testCreate
     */
    public function testFindOneNoResult()
    {
        $doc = Doc1::find(['foo' => 'xxx'])->first();
        $this->assertNull($doc);
    }

    /**
     *  @dependsOn testFindOneWithResult
     */
    public function testUpdate()
    {
        $doc = Doc1::newQuery()->where(['bar' => 'lol'])->first();
        $doc->bar = 'xxx';
        $doc->save();

        $this->assertNull(Doc1::find(['bar' => 'lol'])->first());
        $this->assertNotNull(Doc1::find(['bar' => 'xxx'])->first());
    }

    /**
     *  @dependsOn testFindOneWithResult
     */
    public function testQueryByPHPProperty()
    {
        $doc = Doc1::create(['bar' => 'lolxxx']);
        $this->assertEquals($doc, Doc1::find(['id' => $doc->id])->first());
        $this->assertEquals('id', Doc1::getKeyName());
        $this->assertEquals('id', $doc->getKeyName());
    }

    public function testMany()
    {
        $x = Doc1::find();
        $this->assertTrue($x instanceof Tuicha\Query\Query);
        $total = 0;
        foreach (Doc1::find() as $key => $row) {
            $this->assertTrue($row instanceof Doc1);
            ++$total;
        }
        $this->assertNotEquals(0, $total);
    }

    public function testId()
    {
        $x = new Doc4;
        $x->foo = 'bar';
        $x->save();

        foreach (Doc4::find() as $id => $obj) {
           $this->assertEquals($id, $obj->getId());
        }
    }

    public function testIdString()
    {
        $x = new Doc4;
        $x->foo = uniqid();
        $x->save();

        $this->assertEquals($x->foo, Doc4::find()->where('_id', (string)$x->getId())->first()->foo);
    }

    public function testFindLimit()
    {
        for ($i=0; $i < 100; ++$i) {
            $x = new Doc4;
            $x->foo = uniqid(true);
            $x->save();
        }

        $this->assertTrue(Doc4::count() >= 100);
        $this->assertEquals(10, count(iterator_to_array(Doc4::find()->limit(10))));
    }
}
