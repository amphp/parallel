<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\SharedObject;
use Icicle\Tests\Concurrent\TestCase;

class SharedObjectTest extends TestCase
{
    public function testConstructor()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertInternalType('object', $object->deref());
        $object->free();
    }

    public function testDerefIsOfCorrectType()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertInstanceOf('stdClass', $object->deref());
        $object->free();
    }

    public function testDerefIsEqual()
    {
        $object = new \stdClass();
        $shared = new SharedObject($object);
        $this->assertEquals($object, $shared->deref());
        $shared->free();
    }

    public function testNewObjectIsNotFreed()
    {
        $object = new SharedObject(new \stdClass());
        $this->assertFalse($object->isFreed());
        $object->free();
    }

    public function testFreeReleasesObject()
    {
        $object = new SharedObject(new \stdClass());
        $object->free();
        $this->assertTrue($object->isFreed());
    }

    public function testUpdate()
    {
        $object = new \stdClass();
        $object->foo = 3;
        $shared = new SharedObject($object);
        $this->assertEquals(3, $shared->deref()->foo);
        $shared->free();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\SharedMemoryException
     */
    public function testDerefThrowsErrorIfFreed()
    {
        $object = new SharedObject(new \stdClass());
        $object->free();
        $object->deref();
    }

    public function testCloneIsNewObject()
    {
        $object = new \stdClass();
        $shared = new SharedObject($object);
        $clone = clone $shared;
        $this->assertNotSame($shared, $clone);
        $this->assertNotSame($object, $clone->deref());
        $this->assertNotEquals($shared->__debugInfo()['id'], $clone->__debugInfo()['id']);
        $shared->free();
    }
}
