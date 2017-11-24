<?php

use Docs\Doc1;

class FindTest extends PHPUnit\Framework\TestCase
{
    public function testFindOrCreate()
    {
        $where = ['email' => uniqid() . '@name.com', 'name' => 'lol'];
        $user1 = User::firstOrCreate($where);
        $this->assertEquals($where['email'], $user1->email);
        $this->assertEquals($user1->id, User::firstOrCreate($where)->id);
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
        $doc = Doc1::find(['bar' => 'lol'])->first();
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
        $doc = Doc1::find(['bar' => 'lol'])->first();
        $doc->bar = 'xxx';
        $doc->save();

        $this->assertNull(Doc1::find(['bar' => 'lol'])->first());
        $this->assertNotNull(Doc1::find(['bar' => 'xxx'])->first());
    }

    public function testMany()
    {
        $x = Doc1::find();
        $this->assertTrue($x instanceof Tuicha\Query);
        $total = 0;
        foreach (Doc1::find() as $row) {
            $this->assertTrue($row instanceof Doc1);
            ++$total;
        }
        $this->assertNotEquals(0, $total);
    }

}
