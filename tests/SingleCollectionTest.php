<?php

use Tuicha\Metadata;

class SingleCollectionTest extends PHPUnit\Framework\TestCase
{
    public function testCollectionName()
    {
        $this->assertEquals('users', Metadata::of('Admin')->getCollectionName());
        $this->assertEquals('users', Metadata::of('User')->getCollectionName());
        $this->assertEquals('users', Metadata::of('SuperAdmin')->getCollectionName());
    }

    public function testSaveAndFind()
    {
        Admin::truncate();
        $user = new User;
        $user->name = 'foo';
        $user->email = uniqid(true) . '@test.com';
        $user->save();

        $admin = new Admin;
        $admin->name  = 'admin';
        $admin->email = uniqid(true) . '@test.com';
        $admin->save();

        $this->assertEquals(Admin::class, get_class(User::find(['id' => $admin->id])->first()));
    }

    public function testFindByAdmin()
    {
        Admin::truncate();
        $user = new User;
        $user->name = 'foo';
        $user->email = uniqid(true) . '@test.com';
        $user->save();

        $admin = new Admin;
        $admin->name  = 'admin';
        $admin->email = uniqid(true) . '@test.com';
        $admin->save();

        $this->assertNull(Admin::find(['id' => $user->id])->first());
        $this->assertEquals(Admin::class, get_class(Admin::find()->first()));
    }

    public function testDelete()
    {
        $user = new User;
        $user->name = 'foo';
        $user->email = uniqid(true) . '@test.com';
        $user->save();

        Admin::truncate();
        $this->assertEquals(0, Admin::count());
        $this->assertNotEquals(0, User::count());
    }
}
