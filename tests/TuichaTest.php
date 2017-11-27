<?php


class TuichaTest extends PHPUnit\Framework\TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function testInvalidConnectionName()
    {
        Tuicha::getConnection(uniqid());
    }

    public function testAddConnection()
    {
        $db = uniqid();
        Tuicha::addConnection($db, 'mongodb://localhost:27017', $db);

        $conn = Tuicha::getConnection($db);
        $this->assertTrue($conn instanceof Tuicha\Database);
        $this->assertTrue(Tuicha::dropDatabase($db));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testAddDirectoryExcpetion() {
        Tuicha::addDirectory(__FILE__);
    }

    public function testAddDirectory()
    {
        $this->assertFalse(class_exists('ClassWithoutAnyOutloader'));
        Tuicha::addDirectory(__DIR__ . '/docs2');
        $this->assertTrue(class_exists('ClassWithoutAnyOutloader'));
    }
}
