<?php

use Tuicha\Metadata;

class demo2 {
}

class demo1 {
    use Tuicha\Document;

    /** @Id */
    public $id;

    /** @Class(demo2) */
    protected $bar;

    public function getBar()
    {
        return $this->bar;
    }
}

define('demo1_class', 'demo1');

class MetadataTest extends PHPUnit\Framework\TestCase
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

    public function testGetIndexes()
    {
        $indexes = Metadata::of('User')->getIndexes();
        $this->assertEquals([
            [
                'key' => ['name' => 1],
                'unique' => false,
                'sparse' => true,
                'background' => true,
                'name' => 'index_name_asc',
            ],
            [
                'key' => ['email' => 1],
                'unique' => true,
                'sparse' => false,
                'background' => true,
                'name' => 'unique_email_asc',
            ]
        ], $indexes);
    }

    public function testGetTuichaMetadata()
    {
        $this->assertEquals(
            User::getTuichaMetadata(),
            Metadata::of(User::class)
        );
    }

    public function testPropertiesByAnnotation()
    {
        $properties = User::getTuichaMetadata()->getPropertiesByAnnotation('@required');
        $this->assertTrue(is_array($properties));
        $this->assertFalse(empty($properties));
        $this->assertTrue(array_key_exists('name', $properties));
        $properties = User::getTuichaMetadata()->getPropertiesByAnnotation('@requiredxxxo');
        $this->assertEquals([], $properties);
    }

    public function testgetPropertyValue()
    {
        $user = new User;
        $user->xxx = false;
        $meta = User::getTuichaMetadata();
        $this->assertEquals($user->xxx, $meta->getPropertyValue($user, 'xxx'));
        $this->assertNull($meta->getPropertyValue($user, 'yyy'));
    }
}
