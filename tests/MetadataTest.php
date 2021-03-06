<?php

use Tuicha\Metadata;

class demo2 {
}

class demo1 {
    use Tuicha\Document;

    /** @Id */
    public $id;

    /** @Type(class="demo2") */
    protected $bar;

    public function getBar()
    {
        return $this->bar;
    }
}

define('demo1_class', 'demo1');

class MetadataTest extends PHPUnit_Framework_TestCase
{
    public function testLoading()
    {
        $meta = Metadata::of(demo1_class);
        $this->assertTrue($meta instanceof Metadata);
        $this->assertEquals($meta, Metadata::of(new demo1));
        $this->assertEquals($meta->getFile(), __FILE__);
        $this->assertEquals(
            spl_object_hash($meta), 
            spl_object_hash(Metadata::of(new demo1))
        );
    }

    /**
     * @expectedException RuntimeException
     */
    public function testLoadingException()
    {
        Metadata::of('foobar_class_doesnt_exists');
    }

    public function testPopulation()
    {
        $rand = uniqid(true);
        $meta = Metadata::of(demo1_class);

        $obj = $meta->newInstance(['_id' => $rand, $rand => $rand]);
        $this->assertTrue($obj instanceof Demo1);
        $this->assertEquals($obj->id, $obj->$rand);
    }

    public function testNestedPopulation()
    {
        $rand = uniqid(true);
        $meta = Metadata::of(demo1_class);
        $obj = $meta->newInstance(['bar' => ['foo' => $rand]]);
        $this->assertNull($obj->id);
        $this->assertTrue($obj->getbar() instanceof Demo2);
        $this->assertEquals($obj->getbar()->foo, $rand);
    }

    public function testRead()
    {
        $rand = uniqid(true);
        $meta = Metadata::of(demo1_class);
        $obj1 = $meta->newInstance(['bar' => ['foo' => $rand], '_id' => $rand]);
        $arr1 = $meta->toDocument($obj1);
        $this->assertEquals(['_id' => $rand, 'bar' => ['foo' => $rand]], $arr1);
    }

    public function testCollectionName()
    {
        $this->assertEquals('users', Metadata::of('User')->getCollectionName());
    }
}
